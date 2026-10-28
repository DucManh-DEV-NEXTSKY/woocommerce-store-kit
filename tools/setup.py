#!/usr/bin/env python3
"""Create a fresh Store Kit WordPress instance; never clone a merchant database."""
from __future__ import annotations
import argparse
import getpass
import hashlib
import importlib.util
import json
import os
import re
import secrets
import shutil
import subprocess
import sys
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CORE_NAMES = ('woocommerce', 'sqlite-database-integration', 'store-kit', 'store-kit-veterinary')
THEME_NAMES = ('blocksy', 'store-kit-child')


def php_string(value: str) -> str:
    return "'" + value.replace('\\', '\\\\').replace("'", "\\'") + "'"


def fetch(url: str) -> bytes:
    with urllib.request.urlopen(url, timeout=180) as response:
        if not response.url.startswith('https://'):
            raise ValueError('Runtime download must use HTTPS')
        return response.read()


def php_runtime(explicit: str | None) -> tuple[str, list[str]]:
    if explicit:
        path = Path(explicit).resolve()
        if not path.is_file():
            raise ValueError('The selected PHP executable does not exist')
        return str(path), []
    installed = shutil.which('php')
    if installed:
        return installed, []
    if sys.platform != 'win32':
        raise ValueError('Install PHP 8.1+ CLI with mysqli/curl/mbstring/xml/zip (and pdo_sqlite for local preview), or pass --php.')
    runtime = ROOT / '.work/php'
    executable = runtime / 'php.exe'
    if not executable.is_file():
        versions = json.loads(fetch('https://windows.php.net/downloads/releases/releases.json'))
        item = versions['8.3']['nts-vs16-x64']['zip']
        content = fetch('https://downloads.php.net/~windows/releases/' + item['path'])
        if hashlib.sha256(content).hexdigest() != item['sha256']:
            raise ValueError('Portable PHP checksum failed')
        import zipfile
        import io
        runtime.mkdir(parents=True, exist_ok=True)
        with zipfile.ZipFile(io.BytesIO(content)) as archive:
            for entry in archive.infolist():
                if not (runtime / entry.filename).resolve().is_relative_to(runtime.resolve()):
                    raise ValueError('Unsafe PHP archive')
            archive.extractall(runtime)
    cacert = runtime / 'cacert.pem'
    if not cacert.is_file():
        cacert.write_bytes(fetch('https://curl.se/ca/cacert.pem'))
    ini = runtime / 'store-kit.ini'
    extension_dir = (runtime / 'ext').resolve().as_posix()
    extensions = ('curl', 'fileinfo', 'gd', 'intl', 'mbstring', 'mysqli', 'openssl', 'pdo_sqlite', 'sqlite3', 'zip')
    text = 'memory_limit=512M\nmax_execution_time=180\nupload_max_filesize=32M\npost_max_size=36M\n'
    text += f'extension_dir="{extension_dir}"\n'
    text += ''.join('extension=' + name + '\n' for name in extensions)
    text += 'curl.cainfo="' + cacert.resolve().as_posix() + '"\nopenssl.cafile="' + cacert.resolve().as_posix() + '"\n'
    ini.write_text(text, encoding='utf-8')
    # Passing Unicode paths through argv avoids the Windows PHP DLL loader's INI-path encoding issue.
    return str(executable.resolve()), ['-c', str(ini.resolve()), '-d', 'extension_dir=' + extension_dir, '-d', 'curl.cainfo=' + cacert.resolve().as_posix(), '-d', 'openssl.cafile=' + cacert.resolve().as_posix()]


def build_module():
    spec = importlib.util.spec_from_file_location('store_kit_build', ROOT / 'tools/build.py')
    module = importlib.util.module_from_spec(spec)
    assert spec.loader
    spec.loader.exec_module(module)
    return module


def materialize(target: Path) -> Path:
    """Copy code only. Even when run inside an installed instance, never copy its config/uploads/DB."""
    if (target / 'wp-config.php').exists():
        marker = target / '.store-kit-instance.json'
        if not marker.is_file() or json.loads(marker.read_text(encoding='utf-8')).get('format') != 1:
            raise ValueError('Refusing an existing WordPress installation not created by this setup tool')
        return target
    bundled = ROOT.parent
    if (bundled / 'wp-load.php').is_file() and (bundled / 'wp-content/plugins/store-kit/store-kit.php').is_file():
        if target != bundled:
            if target.exists() and any(target.iterdir()):
                raise ValueError('Destination must be empty; unrelated files are never overwritten')
            target.mkdir(parents=True, exist_ok=True)
            for name in ('wp-admin', 'wp-includes'):
                shutil.copytree(bundled / name, target / name)
            for file in bundled.iterdir():
                if file.is_file() and (file.name.startswith('wp-') or file.name in ('index.php', 'xmlrpc.php', 'license.txt', 'readme.html')) and file.name != 'wp-config.php':
                    shutil.copy2(file, target / file.name)
            for name in CORE_NAMES:
                shutil.copytree(bundled / 'wp-content/plugins' / name, target / 'wp-content/plugins' / name)
            for name in THEME_NAMES:
                shutil.copytree(bundled / 'wp-content/themes' / name, target / 'wp-content/themes' / name)
        return target
    if target.exists() and any(target.iterdir()):
        raise ValueError('Destination must be empty or an owned Store Kit instance')
    builder = build_module()
    lock = builder.fetch_dependencies(False)
    stage = ROOT / '.work/setup-source'
    if stage.exists():
        shutil.rmtree(stage)
    stage.mkdir(parents=True)
    builder.extract_zip(builder.VENDOR / lock['dependencies']['wordpress']['archive'], stage)
    source = stage / 'wordpress'
    shutil.rmtree(source / 'wp-content/plugins')
    shutil.rmtree(source / 'wp-content/themes')
    for name, directory in (('woocommerce', 'plugins'), ('sqlite-database-integration', 'plugins'), ('blocksy', 'themes')):
        builder.extract_zip(builder.VENDOR / lock['dependencies'][name]['archive'], source / 'wp-content' / directory)
    shutil.copytree(ROOT / 'components/plugins', source / 'wp-content/plugins', dirs_exist_ok=True)
    shutil.copytree(ROOT / 'components/themes', source / 'wp-content/themes', dirs_exist_ok=True)
    target.parent.mkdir(parents=True, exist_ok=True)
    if target.exists():
        target.rmdir()
    shutil.move(str(source), str(target))
    stage.rmdir()
    return target


def config(target: Path, database: str, url: str) -> None:
    file = target / 'wp-config.php'
    if file.exists():
        return
    if database == 'mysql':
        required = ('STORE_KIT_DB_NAME', 'STORE_KIT_DB_USER', 'STORE_KIT_DB_PASSWORD')
        missing = [name for name in required if not os.environ.get(name)]
        if missing:
            raise ValueError('Missing database environment variables: ' + ', '.join(missing))
        db = dict(name=os.environ['STORE_KIT_DB_NAME'], user=os.environ['STORE_KIT_DB_USER'], password=os.environ['STORE_KIT_DB_PASSWORD'], host=os.environ.get('STORE_KIT_DB_HOST', 'localhost'))
    else:
        db = dict(name='store_kit', user='', password='', host='localhost')
    text = (target / 'wp-config-sample.php').read_text(encoding='utf-8')
    for constant, value in (('DB_NAME', db['name']), ('DB_USER', db['user']), ('DB_PASSWORD', db['password']), ('DB_HOST', db['host'])):
        text = re.sub(r"define\(\s*'" + constant + r"'\s*,\s*'[^']*'\s*\);", lambda _: f'define( {php_string(constant)}, {php_string(value)} );', text)
    for key in ('AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'):
        text = re.sub(r"define\(\s*'" + key + r"'\s*,\s*'[^']*'\s*\);", lambda _: f'define( {php_string(key)}, {php_string(secrets.token_urlsafe(48))} );', text)
    extra = "define( 'DISALLOW_FILE_EDIT', true );\ndefine( 'WP_ENVIRONMENT_TYPE', '" + ('local' if database == 'sqlite' else 'production') + "' );\n"
    extra += "define( 'DISABLE_WP_CRON', true );\n" if database == 'sqlite' else ''
    if database == 'sqlite':
        data = target.parent / ('.' + target.name + '-sqlite')
        data.mkdir(parents=True, exist_ok=True)
        extra += "define( 'DB_PATH', " + php_string((data / 'store-kit.sqlite').resolve().as_posix()) + " );\n"
        template = target / 'wp-content/plugins/sqlite-database-integration/db.copy'
        if not template.is_file():
            raise ValueError('SQLite integration dependency is missing')
        dropin = template.read_text(encoding='utf-8').replace('{SQLITE_IMPLEMENTATION_FOLDER_PATH}', (target / 'wp-content/plugins/sqlite-database-integration').resolve().as_posix()).replace('{SQLITE_PLUGIN}', 'sqlite-database-integration/load.php')
        (target / 'wp-content/db.php').write_text(dropin, encoding='utf-8')
    text = text.replace("/* That's all, stop editing! Happy publishing. */", extra + "\n/* That's all, stop editing! Happy publishing. */")
    file.write_text(text, encoding='utf-8')
    if os.name != 'nt':
        file.chmod(0o600)
    (target / '.store-kit-instance.json').write_text(json.dumps({'format': 1, 'database': database, 'url': url}) + '\n', encoding='utf-8')


def wp_cli() -> Path:
    file = ROOT / 'tools/wp-cli.phar'
    if file.is_file():
        return file
    builder = build_module()
    lock = builder.fetch_dependencies(False)
    return builder.VENDOR / lock['dependencies']['wp-cli']['archive']


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--path', required=True, type=Path, help='Empty destination, unconfigured bundled root, or owned instance')
    parser.add_argument('--url', required=True)
    parser.add_argument('--title', required=True)
    parser.add_argument('--admin-user', required=True)
    parser.add_argument('--admin-email', required=True)
    parser.add_argument('--database', choices=('mysql', 'sqlite'), default='mysql')
    parser.add_argument('--preset', choices=('neutral', 'pigvet'), default='neutral')
    parser.add_argument('--demo', action='store_true', help='Create clearly marked, purchase-blocked demonstration products')
    parser.add_argument('--php', help='Existing PHP CLI executable (must have the required extensions)')
    parser.add_argument('--serve', action='store_true', help='After SQLite preview setup, serve it on the loopback URL until interrupted')
    args = parser.parse_args()
    try:
        parts = urllib.parse.urlsplit(args.url)
        if parts.scheme not in ('http', 'https') or not parts.hostname or parts.username or parts.password or parts.query or parts.fragment:
            raise ValueError('Use a plain http(s) site URL without credentials/query/fragment')
        if args.database == 'sqlite' and parts.hostname not in ('localhost', '127.0.0.1', '::1'):
            raise ValueError('SQLite preview is limited to loopback URLs; use MySQL for hosted stores')
        if args.serve and (args.database != 'sqlite' or parts.scheme != 'http' or parts.hostname != '127.0.0.1' or parts.path not in ('', '/')):
            raise ValueError('--serve requires a SQLite preview URL on http://127.0.0.1:PORT with no subdirectory')
        if not re.fullmatch(r'[A-Za-z0-9_.-]+', args.admin_user) or '@' not in args.admin_email:
            raise ValueError('A valid administrator username and email are required')
        target = args.path.resolve()
        password = os.environ.get('STORE_KIT_ADMIN_PASSWORD') or getpass.getpass('New store administrator password: ')
        if len(password) < 16:
            raise ValueError('Use an administrator password of at least 16 characters')
        php, php_args = php_runtime(args.php)
        cli = wp_cli()
        materialize(target)
        marker = target / '.store-kit-instance.json'
        if marker.is_file() and json.loads(marker.read_text(encoding='utf-8')).get('url') != args.url:
            raise ValueError('An existing instance has a different URL; refusing a silent database URL change')
        config(target, args.database, args.url)
        base = [php, *php_args, str(cli), '--path=' + str(target), '--url=' + args.url]
        def run(*arguments: str, input_text: str | None = None, check: bool = True):
            result = subprocess.run(base + list(arguments), input=input_text, text=True, encoding='utf-8', errors='replace', capture_output=True)
            # Only WP-CLI output is printed; command arguments and passwords are never echoed.
            if result.stdout:
                print(result.stdout.rstrip())
            if result.stderr:
                print(result.stderr.rstrip(), file=sys.stderr)
            if check and result.returncode:
                raise ValueError('WP-CLI failed; fix the reported prerequisite and re-run the same owned instance')
            return result
        installed = run('core', 'is-installed', check=False).returncode == 0
        if not installed:
            install_input = {'root': str(target), 'url': args.url, 'title': args.title, 'user': args.admin_user, 'email': args.admin_email, 'password': password}
            run('eval-file', str(ROOT / 'tools/install.php'), '--skip-wordpress', input_text=json.dumps(install_input))
        run('plugin', 'activate', 'woocommerce', 'store-kit')
        run('theme', 'activate', 'store-kit-child')
        command = ['store-kit', 'setup', '--preset=' + args.preset]
        if args.demo:
            command.append('--demo')
        run(*command)
        run('option', 'update', 'blog_public', '0')
        run('option', 'update', 'woocommerce_allow_tracking', 'no')
        run('option', 'update', 'woocommerce_coming_soon', 'no' if args.database == 'sqlite' else 'yes')
        run('store-kit', 'doctor')
        print('Ready: ' + args.url + '/wp-admin/admin.php?page=store-kit-settings')
        print('No merchant database was cloned. Complete legal/contact/payment/shipping data before opening the store.')
        if args.serve:
            port = parts.port or 8793
            print(f'Preview server: http://127.0.0.1:{port} (Ctrl+C to stop)', flush=True)
            try:
                return subprocess.call([php, *php_args, '-S', f'127.0.0.1:{port}', '-t', str(target), str(ROOT / 'tools/router.php')])
            except KeyboardInterrupt:
                return 0
        return 0
    except (OSError, ValueError, KeyError, json.JSONDecodeError) as error:
        print('Setup stopped: ' + str(error), file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
