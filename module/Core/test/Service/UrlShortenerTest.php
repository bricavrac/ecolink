<?php

declare(strict_types=1);

namespace ShlinkioTest\Shlink\Core\Service;

use Cake\Chronos\Chronos;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Shlinkio\Shlink\Core\Entity\ShortUrl;
use Shlinkio\Shlink\Core\Entity\Tag;
use Shlinkio\Shlink\Core\Exception\NonUniqueSlugException;
use Shlinkio\Shlink\Core\Model\ShortUrlMeta;
use Shlinkio\Shlink\Core\Repository\ShortUrlRepository;
use Shlinkio\Shlink\Core\Service\ShortUrl\ShortCodeHelperInterface;
use Shlinkio\Shlink\Core\Service\UrlShortener;
use Shlinkio\Shlink\Core\ShortUrl\Resolver\SimpleShortUrlRelationResolver;
use Shlinkio\Shlink\Core\Util\UrlValidatorInterface;

class UrlShortenerTest extends TestCase
{
    use ProphecyTrait;

    private UrlShortener $urlShortener;
    private ObjectProphecy $em;
    private ObjectProphecy $urlValidator;
    private ObjectProphecy $shortCodeHelper;

    public function setUp(): void
    {
        $this->urlValidator = $this->prophesize(UrlValidatorInterface::class);
        $this->urlValidator->validateUrl('http://foobar.com/12345/hello?foo=bar', null)->will(
            function (): void {
            },
        );

        $this->em = $this->prophesize(EntityManagerInterface::class);
        $this->em->persist(Argument::any())->will(function ($arguments): void {
            /** @var ShortUrl $shortUrl */
            [$shortUrl] = $arguments;
            $shortUrl->setId('10');
        });
        $this->em->transactional(Argument::type('callable'))->will(function (array $args) {
            /** @var callable $callback */
            [$callback] = $args;

            return $callback();
        });
        $repo = $this->prophesize(ShortUrlRepository::class);
        $repo->shortCodeIsInUse(Argument::cetera())->willReturn(false);
        $this->em->getRepository(ShortUrl::class)->willReturn($repo->reveal());

        $this->shortCodeHelper = $this->prophesize(ShortCodeHelperInterface::class);
        $this->shortCodeHelper->ensureShortCodeUniqueness(Argument::cetera())->willReturn(true);

        $this->urlShortener = new UrlShortener(
            $this->urlValidator->reveal(),
            $this->em->reveal(),
            new SimpleShortUrlRelationResolver(),
            $this->shortCodeHelper->reveal(),
        );
    }

    /** @test */
    public function urlIsProperlyShortened(): void
    {
        $shortUrl = $this->urlShortener->shorten(
            ShortUrlMeta::fromRawData(['longUrl' => 'http://foobar.com/12345/hello?foo=bar']),
        );

        self::assertEquals('http://foobar.com/12345/hello?foo=bar', $shortUrl->getLongUrl());
    }

    /** @test */
    public function exceptionIsThrownWhenNonUniqueSlugIsProvided(): void
    {
        $ensureUniqueness = $this->shortCodeHelper->ensureShortCodeUniqueness(Argument::cetera())->willReturn(false);

        $ensureUniqueness->shouldBeCalledOnce();
        $this->expectException(NonUniqueSlugException::class);

        $this->urlShortener->shorten(ShortUrlMeta::fromRawData(
            ['customSlug' => 'custom-slug', 'longUrl' => 'http://foobar.com/12345/hello?foo=bar'],
        ));
    }

    /**
     * @test
     * @dataProvider provideExistingShortUrls
     */
    public function existingShortUrlIsReturnedWhenRequested(ShortUrlMeta $meta, ShortUrl $expected): void
    {
        $repo = $this->prophesize(ShortUrlRepository::class);
        $findExisting = $repo->findOneMatching(Argument::cetera())->willReturn($expected);
        $getRepo = $this->em->getRepository(ShortUrl::class)->willReturn($repo->reveal());

        $result = $this->urlShortener->shorten($meta);

        $findExisting->shouldHaveBeenCalledOnce();
        $getRepo->shouldHaveBeenCalledOnce();
        $this->em->persist(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->urlValidator->validateUrl(Argument::cetera())->shouldNotHaveBeenCalled();
        self::assertSame($expected, $result);
    }

    public function provideExistingShortUrls(): iterable
    {
        $url = 'http://foo.com';

        yield [ShortUrlMeta::fromRawData(['findIfExists' => true, 'longUrl' => $url]), ShortUrl::withLongUrl(
            $url,
        )];
        yield [ShortUrlMeta::fromRawData(
            ['findIfExists' => true, 'customSlug' => 'foo', 'longUrl' => $url],
        ), ShortUrl::withLongUrl($url)];
        yield [
            ShortUrlMeta::fromRawData(['findIfExists' => true, 'longUrl' => $url, 'tags' => ['foo', 'bar']]),
            ShortUrl::withLongUrl($url)->setTags(new ArrayCollection([new Tag('bar'), new Tag('foo')])),
        ];
        yield [
            ShortUrlMeta::fromRawData(['findIfExists' => true, 'maxVisits' => 3, 'longUrl' => $url]),
            ShortUrl::fromMeta(ShortUrlMeta::fromRawData(['maxVisits' => 3, 'longUrl' => $url])),
        ];
        yield [
            ShortUrlMeta::fromRawData(
                ['findIfExists' => true, 'validSince' => Chronos::parse('2017-01-01'), 'longUrl' => $url],
            ),
            ShortUrl::fromMeta(
                ShortUrlMeta::fromRawData(['validSince' => Chronos::parse('2017-01-01'), 'longUrl' => $url]),
            ),
        ];
        yield [
            ShortUrlMeta::fromRawData(
                ['findIfExists' => true, 'validUntil' => Chronos::parse('2017-01-01'), 'longUrl' => $url],
            ),
            ShortUrl::fromMeta(
                ShortUrlMeta::fromRawData(['validUntil' => Chronos::parse('2017-01-01'), 'longUrl' => $url]),
            ),
        ];
        yield [
            ShortUrlMeta::fromRawData(['findIfExists' => true, 'domain' => 'example.com', 'longUrl' => $url]),
            ShortUrl::fromMeta(ShortUrlMeta::fromRawData(['domain' => 'example.com', 'longUrl' => $url])),
        ];
        yield [
            ShortUrlMeta::fromRawData([
                'findIfExists' => true,
                'validUntil' => Chronos::parse('2017-01-01'),
                'maxVisits' => 4,
                'longUrl' => $url,
                'tags' => ['baz', 'foo', 'bar'],
            ]),
            ShortUrl::fromMeta(ShortUrlMeta::fromRawData([
                'validUntil' => Chronos::parse('2017-01-01'),
                'maxVisits' => 4,
                'longUrl' => $url,
            ]))->setTags(new ArrayCollection([new Tag('foo'), new Tag('bar'), new Tag('baz')])),
        ];
    }
}
