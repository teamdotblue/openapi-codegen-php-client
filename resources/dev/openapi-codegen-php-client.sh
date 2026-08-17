#!/bin/bash
set -e

argv0=$(echo "$0" | sed -e 's,\\,/,g')
generatordir=$(dirname "$(readlink "$0" || echo "$argv0")")

use_winpty=0

case "$(uname -s)" in
  *CYGWIN*) generatordir=$(cygpath -w "$basedir");;
  MSYS*|MINGW*) use_winpty=1;;
  Linux) generatordir=$(dirname "$(readlink -f "$0" || echo "$argv0")");;
esac

generatordir=$(cd $(dirname $argv0) > /dev/null && cd $generatordir > /dev/null && pwd)
generatorimage=team-blue/java-openapi-codegen-php-client
rootdir=`cd $(dirname $argv0)/../../../../../; pwd`

cd "${generatordir}" && DOCKER_BUILDKIT=1 docker build --target runner -t ${generatorimage} openapi-codegen-php-client

if [ -x "${rootdir}/resources/scripts/before_run.sh" ]
then
  "${rootdir}"/resources/scripts/before_run.sh
fi

docker run --rm --user "$(id -u):$(id -g)" -v "${rootdir}":/local ${generatorimage} generate -g openapi-codegen-php-client \
                                                               -i /local/resources/api/api-spec.json \
                                                               -o /local/ \
                                                               -c /local/resources/api/config.json \
                                                               -t /local/resources/api/templates 1>/dev/null

# Create the factories
docker run --rm --user "$(id -u):$(id -g)" -v "${rootdir}":/local ${generatorimage} generate -g openapi-codegen-php-client \
                                                               -i /local/resources/api/api-spec.json \
                                                               -o /local/ \
                                                               -c /local/resources/api/config.json \
                                                               -t /local/resources/api/templates \
                                                               --additional-properties=factories=true \
                                                               --global-property=models 1>/dev/null

# Create the defaults
docker run --rm --user "$(id -u):$(id -g)" -v "${rootdir}":/local ${generatorimage} generate -g openapi-codegen-php-client \
                                                               -i /local/resources/api/api-spec.json \
                                                               -o /local/ \
                                                               -c /local/resources/api/config.json \
                                                               -t /local/resources/api/templates \
                                                               --additional-properties=defaults=true \
                                                               --global-property=models \
                                                               --skip-overwrite 1>/dev/null

# Exit code of phpcbf is 1 if all is fixed https://github.com/squizlabs/PHP_CodeSniffer/issues/2898
cd "${rootdir}" && (vendor/bin/phpcbf --extensions=php ./Client.php ./ClientMock.php ./ApiClientMock.php ./ClientInterface.php Model/ Endpoint/ Factory/ Default/ 1> /dev/null || true)
cd "${rootdir}" && vendor/bin/rector --no-progress-bar --output-format=json

if [ -x "${rootdir}/resources/scripts/after_run.sh" ]
then
  "${rootdir}"/resources/scripts/after_run.sh
fi


