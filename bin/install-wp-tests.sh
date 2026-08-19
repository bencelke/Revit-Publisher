#!/usr/bin/env bash
set -euo pipefail

DB_NAME="${1:-wordpress_test}"
DB_USER="${2:-wordpress}"
DB_PASS="${3:-wordpress}"
DB_HOST="${4:-db}"
WP_VERSION="${5:-latest}"

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"

install_wp() {
  if [ -d "$WP_CORE_DIR/wp-includes" ]; then
    return
  fi

  mkdir -p "$WP_CORE_DIR"
  if [ "$WP_VERSION" = 'latest' ]; then
    local ARCHIVE='latest'
  else
    local ARCHIVE="wordpress-$WP_VERSION.tar.gz"
  fi

  curl -sSL "https://wordpress.org/${ARCHIVE}.tar.gz" | tar zx -C "$WP_CORE_DIR" --strip-components=1
}

install_test_suite() {
  if [ -d "$WP_TESTS_DIR/includes" ]; then
    return
  fi

  mkdir -p "$WP_TESTS_DIR"

  local BRANCH="${WP_VERSION}"
  if [ "$WP_VERSION" = 'latest' ]; then
    BRANCH='trunk'
  fi

  if command -v svn >/dev/null 2>&1; then
    svn co --quiet "https://develop.svn.wordpress.org/${BRANCH}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
    svn co --quiet "https://develop.svn.wordpress.org/${BRANCH}/tests/phpunit/data/" "$WP_TESTS_DIR/data"
  else
    curl -sSL "https://github.com/wp-phpunit/wp-phpunit/archive/refs/heads/master.tar.gz" | tar -xz -C /tmp
    cp -r /tmp/wp-phpunit-master/includes "$WP_TESTS_DIR/includes"
    cp -r /tmp/wp-phpunit-master/data "$WP_TESTS_DIR/data" 2>/dev/null || mkdir -p "$WP_TESTS_DIR/data"
  fi
  cp "$WP_TESTS_DIR/includes/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"

  sed -i.bak "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR/wp-tests-config.php"
  sed -i.bak "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
  sed -i.bak "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
  sed -i.bak "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
  sed -i.bak "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
  rm -f "$WP_TESTS_DIR/wp-tests-config.php.bak"
}

install_wp
install_test_suite

echo "WordPress test suite installed."
echo "WP_CORE_DIR=$WP_CORE_DIR"
echo "WP_TESTS_DIR=$WP_TESTS_DIR"
echo "Plugin: $PLUGIN_DIR"
