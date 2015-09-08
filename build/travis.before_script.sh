#!/bin/bash

set -e
shopt -s expand_aliases

# TODO: These should not override any existing environment variables
export WP_TESTS_DIR=/tmp/wordpress-tests/
export PLUGIN_DIR=$(pwd)
export PLUGIN_SLUG=$(basename $(pwd) | sed 's/^wp-//')
export PHPCS_DIR=/tmp/phpcs
export PHPCS_GITHUB_SRC=squizlabs/PHP_CodeSniffer
export PHPCS_GIT_TREE=master
export PHPCS_IGNORE='tests/*,vendor/*,dev-lib/*,app/lib/*,app/helper/class-rt-biz-text-diff.php'
export WPCS_DIR=/tmp/wpcs
export WPCS_GITHUB_SRC=WordPress-Coding-Standards/WordPress-Coding-Standards
export WPCS_GIT_TREE=master
export YUI_COMPRESSOR_CHECK=1
export DISALLOW_EXECUTE_BIT=0
export PATH_INCLUDES=./
export WPCS_STANDARD=$(if [ -e phpcs.ruleset.xml ]; then echo phpcs.ruleset.xml; else echo WordPress-Core; fi)
if [ -e .jscsrc ]; then
	export JSCS_CONFIG=.jscsrc
elif [ -e .jscs.json ]; then
	export JSCS_CONFIG=.jscs.json
fi

# Load a .ci-env.sh to override the above environment variables
if [ -e .ci-env.sh ]; then
	source .ci-env.sh
fi

# Install the WordPress Unit Tests
if [ -e phpunit.xml ] || [ -e phpunit.xml.dist ]; then
	wget -O /tmp/install-wp-tests.sh https://raw.githubusercontent.com/wp-cli/wp-cli/v0.18.0/templates/install-wp-tests.sh
	bash /tmp/install-wp-tests.sh wordpress_test root '' localhost $WP_VERSION
	cd /tmp/wordpress/wp-content/plugins
	mv $PLUGIN_DIR $PLUGIN_SLUG
	cd $PLUGIN_SLUG
	ln -s $(pwd) $PLUGIN_DIR
	echo "Plugin location: $(pwd)"

	if ! command -v phpunit >/dev/null 2>&1; then
		wget -O /tmp/phpunit.phar https://phar.phpunit.de/phpunit.phar
		chmod +x /tmp/phpunit.phar
		alias phpunit='/tmp/phpunit.phar'
	fi
fi

# Install PHP_CodeSniffer and the WordPress Coding Standards
mkdir -p $PHPCS_DIR && curl -L https://github.com/$PHPCS_GITHUB_SRC/archive/$PHPCS_GIT_TREE.tar.gz | tar xvz --strip-components=1 -C $PHPCS_DIR
mkdir -p $WPCS_DIR && curl -L https://github.com/$WPCS_GITHUB_SRC/archive/$WPCS_GIT_TREE.tar.gz | tar xvz --strip-components=1 -C $WPCS_DIR
$PHPCS_DIR/scripts/phpcs --config-set installed_paths $WPCS_DIR

# Install JSHint
if ! command -v jshint >/dev/null 2>&1; then
	npm install -g jshint
fi

# Install jscs
if [ -n "$JSCS_CONFIG" ] && [ -e "$JSCS_CONFIG" ] && ! command -v jscs >/dev/null 2>&1; then
	npm install -g jscs
fi

# Install Composer
if [ -e composer.json ]; then
	wget http://getcomposer.org/composer.phar && php composer.phar install --dev
fi

# P2P Plugin Setup for rtBiz
cd ../
rm -rf posts-to-posts
wget -nv -O posts-to-posts.zip http://downloads.wordpress.org/plugin/posts-to-posts.1.6.3.zip
unzip -q posts-to-posts.zip
cd $PLUGIN_DIR

set +e
