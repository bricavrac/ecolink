#!/usr/bin/env sh
set -e

cd /etc/shlink

flags="--clear-db-cache"

# Skip downloading GeoLite2 db file if the license key env var was not defined or skipping was explicitly set
if [ -z "${GEOLITE_LICENSE_KEY}" ] || [ "${SKIP_INITIAL_GEOLITE_DOWNLOAD}" == "true" ]; then
  flags="${flags} --skip-download-geolite"
fi

php vendor/bin/shlink-installer init ${flags}

# Periodically run visit:locate every hour, if ENABLE_PERIODIC_VISIT_LOCATE=true was provided and running as root
# ENABLE_PERIODIC_VISIT_LOCATE is deprecated. Remove cron support in Shlink 4.0.0
if [ "${ENABLE_PERIODIC_VISIT_LOCATE}" = "true" ] && [ "${SHLINK_USER_ID}" = "root" ]; then
  echo "Configuring periodic visit location..."
  echo "0 * * * * php /etc/shlink/bin/cli visit:locate -q" > /etc/crontabs/root
  /usr/sbin/crond &
fi

if [ "$SHLINK_RUNTIME" == 'openswoole' ]; then
  # When restarting the container, openswoole might think it is already in execution
  # This forces the app to be started every second until the exit code is 0
  until php vendor/bin/laminas mezzio:swoole:start; do sleep 1 ; done
elif [ "$SHLINK_RUNTIME" == 'rr' ]; then
  ./bin/rr serve -c config/roadrunner/.rr.yml
fi
