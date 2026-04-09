FROM timbru31/alpine-java-maven AS builder

WORKDIR /build
COPY . /build/
RUN --mount=type=cache,target=/root/.m2 mvn -q -f /build/pom.xml clean package

FROM openapitools/openapi-generator-cli:v7.21.0 AS runner

ENV OPENAPI_GEN_ROOT_DIR=/opt/openapi-generator
ENV OPENAPI_GEN_JARS_DIR=${OPENAPI_GEN_ROOT_DIR}/jars
ENV OPENAPI_GEN_NAME=openapi-codegen-php-client
ENV PHP_POST_PROCESS_FILE="/local/vendor/bin/phpcbf --extensions=php --report=full"

RUN mkdir -p ${OPENAPI_GEN_JARS_DIR} && cp ${OPENAPI_GEN_ROOT_DIR}/modules/openapi-generator-cli/target/*.jar ${OPENAPI_GEN_JARS_DIR}

COPY --from=builder /build/target/${OPENAPI_GEN_NAME}-1.0.0.jar ${OPENAPI_GEN_JARS_DIR}/${OPENAPI_GEN_NAME}.jar
COPY ./docker-entrypoint.sh /usr/local/bin
