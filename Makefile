app_name := $(notdir $(CURDIR))
build_dir := $(CURDIR)/build/artifacts
sign_dir := $(build_dir)/sign
cert_dir := $(CURDIR)/build/tools/certificates

ifneq (,$(wildcard $(CURDIR)/../nextcloud/occ))
	occ := php $(CURDIR)/../nextcloud/occ
else ifneq (,$(wildcard $(CURDIR)/../../occ))
	occ := php $(CURDIR)/../../occ
endif

.PHONY: all dev-setup build-js build-js-production watch-js clean clean-dev clean-generated-assets clean-production-vendor appstore verify-appstore-package

all: dev-setup build-js-production

dev-setup: clean composer-install npm-install

composer-install:
	composer install

composer-install-production:
	rm -rf vendor
	composer install --no-dev --no-scripts --classmap-authoritative

npm-install:
	npm ci

clean-generated-assets:
	rm -rf css/*
	rm -rf js/*

clean-production-vendor:
	rm -rf vendor

build-js: clean-generated-assets
	npm run dev

build-js-production: clean-generated-assets
	npm run build --if-present

watch-js: clean-generated-assets
	npm run watch

clean:
	rm -rf $(build_dir)

clean-dev: clean
	rm -rf node_modules
	rm -rf vendor

appstore: clean clean-generated-assets clean-production-vendor
	composer install --no-dev --no-scripts --classmap-authoritative
	npm ci
	npm run build --if-present
	mkdir -p $(sign_dir)/$(app_name)
	find $(CURDIR) -mindepth 1 -maxdepth 1 \
		! -name '.git' \
		! -name '.github' \
		! -name '.gitignore' \
		! -name '.l10nignore' \
		! -name '.tx' \
		! -name 'Makefile' \
		! -name 'build' \
		! -name 'env.d.ts' \
		! -name 'node_modules' \
		! -name 'package-lock.json' \
		! -name 'package.json' \
		! -name 'playwright' \
		! -name 'playwright.config.ts' \
		! -name 'playwright-report' \
		! -name 'psalm.xml' \
		! -name 'src' \
		! -name 'test-results' \
		! -name 'tests' \
		! -name 'tsconfig.json' \
		! -name 'vendor-bin' \
		! -name 'vite.config.js' \
		! -name 'vitest.config.js' \
		-exec cp -a {} $(sign_dir)/$(app_name)/ \;
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		curl -fsSL -o $(cert_dir)/$(app_name).crt "https://raw.githubusercontent.com/nextcloud/app-certificate-requests/master/$(app_name)/$(app_name).crt"; \
		$(occ) integrity:sign-app \
			--privateKey=$(cert_dir)/$(app_name).key \
			--certificate=$(cert_dir)/$(app_name).crt \
			--path=$(sign_dir)/$(app_name); \
	fi
	tar -czf $(build_dir)/$(app_name).tar.gz -C $(sign_dir) $(app_name)

verify-appstore-package:
	test -d $(sign_dir)/$(app_name)/appinfo
	test -d $(sign_dir)/$(app_name)/css
	test -d $(sign_dir)/$(app_name)/js
	test -d $(sign_dir)/$(app_name)/lib
	test -d $(sign_dir)/$(app_name)/templates
	test -d $(sign_dir)/$(app_name)/vendor
	test ! -e $(sign_dir)/$(app_name)/src
	test ! -e $(sign_dir)/$(app_name)/tests
	test ! -e $(sign_dir)/$(app_name)/playwright
	test ! -e $(sign_dir)/$(app_name)/node_modules
	test -f $(sign_dir)/$(app_name)/openapi-administration.json
	test -f $(sign_dir)/$(app_name)/openapi-full.json
	test -f $(sign_dir)/$(app_name)/openapi.json
	test -f $(build_dir)/$(app_name).tar.gz
