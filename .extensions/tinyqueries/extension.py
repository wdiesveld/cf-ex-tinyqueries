"""TinyQueries Extension

Downloads, installs and configures TinyQueries
"""
import os
import os.path
import logging
from build_pack_utils import utils


_log = logging.getLogger('tiny-queries')


DEFAULTS = utils.FormattedDict({
    'TINYQUERIES_VERSION': '3.0.7.1',
    'TINYQUERIES_PACKAGE': 'v{TINYQUERIES_VERSION}.tar.gz',
    'TINYQUERIES_HASH': 'dummy',
    'TINYQUERIES_URL': 'https://github.com/wdiesveld/tiny-queries-php-api/archive/{TINYQUERIES_PACKAGE}'
})


# Extension Methods
def preprocess_commands(ctx):
	# This will set the DB-credentials in api/config/config.xml
	ctx['ADDITIONAL_PREPROCESS_CMDS'] = [
		'php $HOME/' + ctx['WEBDIR'] + '/api/config/init-config.php',
	]
	return {}

def service_commands(ctx):
    return {}


def service_environment(ctx):
    return {}


def compile(install):
    print 'Installing TinyQueries %s' % DEFAULTS['TINYQUERIES_VERSION']
    ctx = install.builder._ctx
    inst = install._installer
    workDir = os.path.join(ctx['TMPDIR'], 'tiny-queries-php-api')
    inst.install_binary_direct(
        DEFAULTS['TINYQUERIES_URL'],
        DEFAULTS['TINYQUERIES_HASH'],
        workDir,
        fileName=DEFAULTS['TINYQUERIES_PACKAGE'],
        strip=True)
    (install.builder
        .move()
        .everything()
        .under('{BUILD_DIR}/htdocs/api')
        .into(workDir)
        .done())
    (install.builder
        .move()
        .everything()
        .under(workDir)
        .where_name_does_not_match('^%s/setup/.*$' % workDir)
        .into('{BUILD_DIR}/htdocs/api')
        .done())
    return 0
