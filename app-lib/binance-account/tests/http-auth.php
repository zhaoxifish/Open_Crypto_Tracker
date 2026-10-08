<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/Auth.php';
require dirname(__DIR__) . '/Http.php';

use BtcAccount\Auth;
use BtcAccount\Http;

$results = [];
function check(string $name, bool $passed): void {
    global $results;
    $results[] = $passed;
    echo json_encode(['name'=>$name,'passed'=>$passed],JSON_UNESCAPED_SLASHES) . "\n";
}
function throws(callable $operation): bool {
    try { $operation(); return false; } catch (Throwable) { return true; }
}

$temporaryRoot = realpath(sys_get_temp_dir());
if ($temporaryRoot === false) throw new RuntimeException('No temporary directory.');
$root = $temporaryRoot . '/btc-account-auth-qa-' . bin2hex(random_bytes(6));
if (!mkdir($root,0700)) throw new RuntimeException('Cannot create fixture root.');
$created = [$root];
register_shutdown_function(static function() use (&$created,$root,$temporaryRoot): void {
    if (dirname($root) !== $temporaryRoot || !preg_match('/^btc-account-auth-qa-[a-f0-9]{12}$/D',basename($root))) return;
    foreach (array_reverse($created) as $path) {
        if ($path !== $root && !str_starts_with($path,$root.'/')) continue;
        if (is_link($path) || is_file($path)) unlink($path);
        elseif (is_dir($path) && realpath($path) === $path && count(scandir($path) ?: []) === 2) rmdir($path);
    }
});
$mkdir = static function(string $relative) use ($root,&$created): void {
    $path = $root.'/'.$relative;
    if (!mkdir($path,0700)) throw new RuntimeException('Cannot create fixture directory.');
    $created[] = $path;
};
$write = static function(string $relative,string $contents) use ($root,&$created): void {
    $path = $root.'/'.$relative;
    if (!in_array($path,$created,true)) $created[] = $path;
    if (file_put_contents($path,$contents) !== strlen($contents)) throw new RuntimeException('Cannot write fixture.');
    clearstatcache(true,$path);
};
foreach (['cache','cache/vars','cache/secured','app-lib','app-lib/php','app-lib/php/classes','app-lib/php/classes/3rd-party'] as $directory) $mkdir($directory);
$library = dirname(__DIR__,2).'/php/classes/3rd-party/google-authenticator';
$link = $root.'/app-lib/php/classes/3rd-party/google-authenticator';
if (!symlink($library,$link)) throw new RuntimeException('Cannot link fixture TOTP classes.');
$created[] = $link;

$auth = new Auth($root);
$name = $auth->sessionName();
check('Session name matches the original server install algorithm',$name === 'SESS_SERVER_'.substr(md5('server_session'.$root),0,10));
$nonce=str_repeat('a',64);$cookie=str_repeat('b',64);
$session=['nonce'=>$nonce,'admin_logged_in'=>['auth_hash'=>hash('ripemd160',$cookie.$nonce)]];
$cookies=['admin_auth_'.$name=>$cookie];
check('Original split-cookie administrator authorization remains compatible',Auth::matches($session,$cookies,$name));
foreach (['nonce','admin_logged_in'] as $missing) {
    $bad=$session;unset($bad[$missing]);check('Reject absent session '.$missing,!Auth::matches($bad,$cookies,$name));
}
check('Reject absent administrator cookie',!Auth::matches($session,[],$name));
check('Reject a different administrator cookie',!Auth::matches($session,['admin_auth_'.$name=>str_repeat('c',64)],$name));
check('Reject cookie arrays',!Auth::matches($session,['admin_auth_'.$name=>[$cookie]],$name));
$bad=$session;$bad['nonce']=['invalid'];check('Reject nonce arrays',!Auth::matches($bad,$cookies,$name));
$bad=$session;$bad['nonce']='short';check('Reject short nonce',!Auth::matches($bad,$cookies,$name));
$bad=$session;$bad['nonce']=str_repeat('a',257);check('Reject unbounded nonce',!Auth::matches($bad,$cookies,$name));
$bad=$session;$bad['admin_logged_in']['auth_hash']=false;check('Reject nonstring session digest',!Auth::matches($bad,$cookies,$name));
$bad=$session;$bad['admin_logged_in']['auth_hash']=str_repeat('0',40);check('Reject wrong session digest',!Auth::matches($bad,$cookies,$name));

$_SESSION=['btc_account_csrf'=>str_repeat('d',64)];
check('Accept only the current session CSRF token',$auth->validCsrf(str_repeat('d',64)));
check('Reject missing CSRF',$auth->validCsrf(null)===false);
check('Reject CSRF arrays',$auth->validCsrf(['invalid'])===false);
check('Reject another session CSRF',$auth->validCsrf(str_repeat('e',64))===false);

$oldOrigins=getenv('BTC_ACCOUNT_ORIGINS');putenv('BTC_ACCOUNT_ORIGINS=https://localhost:9443');
$server=['HTTPS'=>'on','HTTP_HOST'=>'localhost:9443','HTTP_SEC_FETCH_SITE'=>'same-origin','HTTP_ORIGIN'=>'https://localhost:9443'];
check('Allow a configured same-origin HTTPS mutation',Http::sameOrigin($server,true));
$bad=$server;unset($bad['HTTPS']);check('Reject cleartext HTTP',!Http::sameOrigin($bad,true));
$bad=$server;$bad['HTTP_HOST']='attacker.invalid';$bad['HTTP_ORIGIN']='https://attacker.invalid';check('Host and Origin agreement cannot bypass the configured origin list',!Http::sameOrigin($bad,true));
$bad=$server;$bad['HTTP_ORIGIN']='https://localhost:8443';check('Reject a different port origin',!Http::sameOrigin($bad,true));
$bad=$server;unset($bad['HTTP_ORIGIN']);check('POST requires an explicit Origin',!Http::sameOrigin($bad,true));
check('Direct authenticated navigation may omit Origin',Http::sameOrigin($bad,false));
$bad=$server;$bad['HTTP_SEC_FETCH_SITE']='cross-site';check('Reject cross-site fetch metadata',!Http::sameOrigin($bad,true));
$bad=$server;$bad['HTTP_SEC_FETCH_SITE']='same-site';check('Reject another origin on the same site',!Http::sameOrigin($bad,true));
putenv($oldOrigins===false?'BTC_ACCOUNT_ORIGINS':'BTC_ACCOUNT_ORIGINS='.$oldOrigins);

check('Missing security mode fails closed',throws(fn()=>$auth->requiresOtp()));
$write('cache/vars/admin_area_2fa.dat','broken');check('Corrupt security mode fails closed',throws(fn()=>$auth->requiresOtp()));
$write('cache/vars/admin_area_2fa.dat','off');check('Disabled two-factor authentication remains compatible',!$auth->requiresOtp());
$write('cache/vars/admin_area_2fa.dat','on');check('Login-only two-factor mode does not become strict mode',!$auth->requiresOtp());
$write('cache/vars/admin_area_2fa.dat','strict');check('Strict mode requires a new operation code',$auth->requiresOtp());
$_SESSION=[];check('Strict mode rejects a missing code',!$auth->checkOtp(null));
$_SESSION=[];check('Strict mode rejects non-six-digit input',!$auth->checkOtp('12345'));
$_SESSION=[];check('Strict mode rejects arrays',!$auth->checkOtp(['123456']));
$_SESSION=[];check('Strict mode rejects unavailable secret files',!$auth->checkOtp('123456'));

$fixtureSecret=str_repeat('c',64);
$write('cache/secured/admin_login_'.str_repeat('a',32).'.dat','qa-admin||synthetic-not-a-password');
$write('cache/secured/secret_var_'.str_repeat('b',32).'.dat',$fixtureSecret);
$write('cache/vars/base_url.dat','https://localhost:9443/');
require_once $library.'/FixedBitNotation.php';
require_once $library.'/GoogleAuthenticatorInterface.php';
require_once $library.'/GoogleAuthenticator.php';
$base32=new Sonata\GoogleAuthenticator\FixedBitNotation(5,'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567',true,true);
$totp=new Sonata\GoogleAuthenticator\GoogleAuthenticator();
$code=$totp->getCode($base32->encode(hash('ripemd160','qa-admin'.'localhost'.$fixtureSecret)));
$_SESSION=[];check('Strict OTP uses the same hostname and hexadecimal digest derivation as upstream',$auth->checkOtp($code));
check('A correct code resets the failed-attempt counter',!isset($_SESSION['btc_account_otp_attempt']));
$_SESSION=['btc_account_otp_attempt'=>['start'=>time(),'count'=>5]];
check('Five failures block even a correct code until the cooldown ends',!$auth->checkOtp($code));
$_SESSION=['btc_account_otp_attempt'=>['start'=>time()-301,'count'=>5]];
check('OTP validation resumes after the cooldown',$auth->checkOtp($code));
$write('cache/vars/base_url.dat','not-a-url');$_SESSION=[];
check('Strict OTP refuses missing canonical hostname',!$auth->checkOtp($code));
$write('cache/vars/base_url.dat','https://localhost:9443/');
$write('cache/secured/secret_var_'.str_repeat('b',32).'.dat','short');$_SESSION=[];
check('Strict OTP refuses corrupt secret data',!$auth->checkOtp($code));

$failed=count(array_filter($results,fn(bool $passed):bool=>!$passed));
echo json_encode(['summary'=>['passed'=>count($results)-$failed,'failed'=>$failed,'total'=>count($results)]]) . "\n";
exit($failed?1:0);
