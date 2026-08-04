.PHONY: *

SHELL = /bin/sh

CURRENT_UID := $(shell id -u)
CURRENT_GID := $(shell id -g)

#
# If doc-base or phd exist as siblings to the current directory, add those as
# volumes to our Docker runs.
#

PATHS := -v ${PWD}:/var/www/extensions
ifneq ($(wildcard ../doc-base/LICENSE),)
	PATHS += -v ${PWD}/../doc-base:/var/www/doc-base
endif
ifneq ($(wildcard ../phd/LICENSE),)
	PATHS += -v ${PWD}/../phd:/var/www/phd
endif

xhtml: .docker/built
	docker run --rm ${PATHS} -w /var/www -u ${CURRENT_UID}:${CURRENT_GID} php/doc-extensions

php: .docker/built
	docker run --rm ${PATHS} -w /var/www -u ${CURRENT_UID}:${CURRENT_GID} \
		-e FORMAT=php php/doc-extensions

build: .docker/built

.docker/built: .docker/Dockerfile
	docker build \
		--build-arg UID=${CURRENT_UID} --build-arg GID=${CURRENT_GID} \
		.docker -t php/doc-extensions
	touch .docker/built
