IMAGE_NAME = "registry.gitlab.com/coincorp/rate-analyzer"

install:

	@docker run --rm -v $(PWD):/app -w /app composer/composer install

build: rmi

	@docker build -t $(IMAGE_NAME) .

rmi:

	@docker rmi $(IMAGE_NAME) 2>/dev/null || true

test:

	@docker run --rm -v $(PWD):/app -w /app phpunit/phpunit --verbose

push:

	@docker push $(IMAGE_NAME)
