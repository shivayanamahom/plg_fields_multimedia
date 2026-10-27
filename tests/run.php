<?php

/**
 * Проверки безопасности и обработки файлов. Запуск на живой установке Joomla:
 *
 *   JOOMLA_ROOT=/path/to/site php tests/run.php
 *
 * Создаёт и удаляет временную папку images/zz-mmtest; базу данных не меняет.
 */

$root = getenv('JOOMLA_ROOT') ?: '';

if ($root === '' || !is_file($root . '/includes/framework.php')) {
    fwrite(STDERR, "Укажите JOOMLA_ROOT — путь к установке Joomla.\n");
    exit(2);
}

$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['SCRIPT_NAME']    = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

define('_JEXEC', 1);
define('JPATH_BASE', $root);
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

$container = \Joomla\CMS\Factory::getContainer();
$container->alias('session.web', 'session.web.site')->alias('session', 'session.web.site')->alias('JSession', 'session.web.site')->alias(\Joomla\Session\Session::class, 'session.web.site')->alias(\Joomla\Session\SessionInterface::class, 'session.web.site');
$app = $container->get(\Joomla\CMS\Application\SiteApplication::class);
\Joomla\CMS\Factory::$application = $app;

JLoader::registerNamespace('Joomla\\Plugin\\Fields\\Multimedia', dirname(__DIR__) . '/src');

use Joomla\Plugin\Fields\Multimedia\Helper\{MediaHelper,UploadHandler,UploadException,ImageProcessor};
use Joomla\Plugin\Fields\Multimedia\Filter\MultimediaFilter;

$fail = 0; $n = 0;
function ok($cond, $msg) { global $fail,$n; $n++; if (!$cond) { $fail++; echo "FAIL: $msg\n"; } }
function throws(callable $f, $msg) { try { $f(); ok(false, "no exception: $msg"); } catch (UploadException $e) { ok(true, $msg); } }

// ---- settings
$s = MediaHelper::settings(['mode'=>'files','max_files'=>'9999','image_quality'=>'5','columns'=>'x','directory'=>'../../etc/PASSWD','file_extensions'=>'pdf, .DOCX,php,svg,exe,zip,a b']);
ok($s['mode']==='files','mode files'); ok($s['max_files']===200,'max_files clamp'); ok($s['image_quality']===40,'quality clamp'); ok($s['columns']===3,'columns default');
ok($s['directory']==='etcpasswd' || $s['directory']==='etc/passwd','dir sanitize: '.$s['directory']);
ok($s['extensions']===['pdf','docx','zip','a','b'],'ext list: '.json_encode($s['extensions']));
ok(MediaHelper::settings(['mode'=>'evil'])['mode']==='gallery','bad mode -> gallery');
ok(MediaHelper::sanitizeDirectory('multimedia-cache/x')==='multimedia','cache dir reserved');
ok(MediaHelper::sanitizeDirectory('a/b/c/d/e/f')==='a/b/c/d','depth 4');

// ---- paths
foreach (['images/a.jpg','images/sub dir/фото 1.jpg','local-images:/x/y.png#joomlaImage://local-images/x/y.png?width=1&height=2'] as $p) ok(MediaHelper::normalizePath($p)!==null,"valid path $p");
foreach (['/etc/passwd','images/../configuration.php','images/a.php','images/a.php.jpg','images/.htaccess','images/x/../../a.jpg','http://evil/a.jpg','images/a.svg','media/a.jpg','images/a<b>.jpg',"images/a\0.jpg",'images/multimedia-cache/x/a.jpg','images/noext','..\\images\\a.jpg','images//..//a.jpg','file:///etc/passwd','images/a.jpg%00.php'] as $p) ok(MediaHelper::normalizePath($p)===null,"bad path ".json_encode($p));
ok(MediaHelper::normalizePath('local-images:/x/y.png#joomlaImage://a')==='images/x/y.png','normalize local-images');

// ---- parseValue
ok(MediaHelper::parseValue('')===[],'empty');
ok(MediaHelper::parseValue('images/old.jpg')[0]['file']==='images/old.jpg','legacy plain');
$legacy = json_encode([['file'=>'images/a.jpg','type'=>'image','alt'=>'<b>x</b>'],['file'=>'../x.php'],'junk']);
$pv = MediaHelper::parseValue($legacy); ok(count($pv)===1 && $pv[0]['alt']==='x','legacy json + strip tags');
ok(MediaHelper::parseValue('{"bad json')===[ ['file'=>'{"bad json'] ] || MediaHelper::parseValue('{"bad json')===[],'bad json safe');

// ---- uploads
$base = JPATH_ROOT.'/images/zz-mmtest'; 
function rrm($d){ if(!is_dir($d))return; foreach(scandir($d) as $f){ if($f==='.'||$f==='..')continue; $p="$d/$f"; is_dir($p)&&!is_link($p)?rrm($p):unlink($p);} rmdir($d);} rrm($base); rrm(JPATH_ROOT.'/images/multimedia-cache');
$tmpdir = sys_get_temp_dir().'/mmt'; @mkdir($tmpdir);
function tmpfile_with($content,$name='u'){ global $tmpdir; $p=$tmpdir.'/'.$name.bin2hex(random_bytes(3)); file_put_contents($p,$content); return $p; }
function up($path,$name){ return ['name'=>$name,'tmp_name'=>$path,'error'=>0,'size'=>filesize($path)]; }
function img($w,$h,$type='jpeg'){ $i=imagecreatetruecolor($w,$h); imagefill($i,0,0,imagecolorallocate($i,200,100,50)); ob_start(); $type==='png'?imagepng($i):($type==='gif'?imagegif($i):imagejpeg($i)); return ob_get_clean(); }

$h = new UploadHandler(false);
$g = MediaHelper::settings(['mode'=>'gallery','directory'=>'zz-mmtest','image_width'=>800,'image_height'=>600,'image_resize'=>'contain']);

$r = $h->handle($g, up(tmpfile_with(img(2000,1000)),'Фото Объект 1.JPG'), 'gal');
ok(preg_match('#^images/zz-mmtest/[a-z0-9_-]+\.jpg$#',$r['file'])===1,'jpg stored: '.$r['file']);
$sz = getimagesize(JPATH_ROOT.'/'.$r['file']); ok($sz[0]===800 && $sz[1]===400,'contain 800x400 got '.$sz[0].'x'.$sz[1]);
ok(is_file($base.'/.htaccess') && is_file($base.'/web.config'),'protection files');

$gc = MediaHelper::settings(['mode'=>'gallery','directory'=>'zz-mmtest','image_width'=>500,'image_height'=>500,'image_resize'=>'cover','image_webp'=>1]);
$r2 = $h->handle($gc, up(tmpfile_with(img(1200,600,'png'),'p'),'pic.png'), 'gal');
$sz = getimagesize(JPATH_ROOT.'/'.$r2['file']); ok(str_ends_with($r2['file'],'.webp') && $sz[0]===500 && $sz[1]===500,'cover webp 500x500: '.$r2['file'].' '.$sz[0].'x'.$sz[1]);
$r3 = $h->handle($gc, up(tmpfile_with(img(300,200),'s'),'small.jpg'), 'gal');
$sz = getimagesize(JPATH_ROOT.'/'.$r3['file']); ok($sz[0]<=300 && $sz[1]<=200,'no upscale cover '.$sz[0].'x'.$sz[1]);
$r4 = $h->handle($gc, up(tmpfile_with(img(300,200),'s'),'small.jpg'), 'gal'); ok($r4['file']!==$r3['file'],'collision gets new name: '.$r4['file']);

// polyglot jpg with php payload
$poly = img(100,100)."\n<?php system(\$_GET['c']); ?>";
$rp = $h->handle($g, up(tmpfile_with($poly),'poly.jpg'), 'gal');
ok(strpos(file_get_contents(JPATH_ROOT.'/'.$rp['file']),'<?php')===false,'polyglot payload stripped');
// attacks
throws(fn()=>$h->handle($g, up(tmpfile_with('<?php echo 1;'),'shell.php'),'x'),'php ext');
throws(fn()=>$h->handle($g, up(tmpfile_with('<?php echo 1;'),'shell.jpg'),'x'),'php content jpg');
throws(fn()=>$h->handle($g, up(tmpfile_with(img(10,10)),'shell.php.jpg'),'x'),'double ext');
throws(fn()=>$h->handle($g, up(tmpfile_with('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),'a.svg'),'x'),'svg');
throws(fn()=>$h->handle($g, up(tmpfile_with(img(10,10)),'real.png'),'x'),'jpeg content with png ext');
throws(fn()=>$h->handle($g, up(tmpfile_with(''),'e.jpg'),'x'),'empty');
throws(fn()=>$h->handle($g, ['name'=>['a','b'],'tmp_name'=>['x','y'],'error'=>[0,0],'size'=>[1,1]],'x'),'array upload');
throws(fn()=>$h->handle($g, ['name'=>'a.jpg','tmp_name'=>'/etc/passwd','error'=>0,'size'=>5],'x') ,'strict? (non-strict handler) /etc/passwd as tmp should fail on type');
throws(fn()=>(new UploadHandler(true))->handle($g, up(tmpfile_with(img(10,10)),'a.jpg'),'x'),'strict mode rejects non-uploaded file');
$big = MediaHelper::settings(['mode'=>'gallery','directory'=>'zz-mmtest','max_size'=>1]);
throws(fn()=>$h->handle($big, up(tmpfile_with(str_repeat('A',1100000)),'big.jpg'),'x'),'oversize');
// decompression bomb: header claiming huge dims
$bomb = img(20,20,'png'); // craft huge IHDR
$bomb = substr($bomb,0,16).pack('N',60000).pack('N',60000).substr($bomb,24);
throws(fn()=>$h->handle($g, up(tmpfile_with($bomb),'bomb.png'),'x'),'giant dimension png');

// files mode
$f = MediaHelper::settings(['mode'=>'files','directory'=>'zz-mmtest/docs','file_extensions'=>'pdf,txt,docx,zip','subfolders'=>'month']);
$pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
$rf = $h->handle($f, up(tmpfile_with($pdf),'Прайс-лист 2026.pdf'),'docs'); ok(preg_match('#^images/zz-mmtest/docs/\d{4}/\d{2}/[a-z0-9_-]+\.pdf$#',$rf['file'])===1,'pdf stored '.$rf['file']);
throws(fn()=>$h->handle($f, up(tmpfile_with('MZ'.str_repeat("\0",100)),'setup.exe'),'x'),'exe');
throws(fn()=>$h->handle($f, up(tmpfile_with('hello <?php echo 1; ?>'),'notes.txt'),'x'),'php in txt');
throws(fn()=>$h->handle($f, up(tmpfile_with('<html><script>alert(1)</script></html>'),'page.html'),'x'),'html');
throws(fn()=>$h->handle($f, up(tmpfile_with('<html><script>alert(1)</script></html>'),'page.pdf'),'x'),'html disguised as pdf');
throws(fn()=>$h->handle($f, up(tmpfile_with('x'),'a.php.pdf'),'x'),'double ext pdf');
throws(fn()=>$h->handle($f, up(tmpfile_with($pdf),'.htaccess'),'x'),'.htaccess');
$rt = $h->handle($f, up(tmpfile_with("plain text\nline"),'readme.txt'),'docs'); ok(is_file(JPATH_ROOT.'/'.$rt['file']),'txt ok');
// no path traversal via name
$rn = $h->handle($f, up(tmpfile_with($pdf),'../../../evil.pdf'),'docs'); ok(str_starts_with($rn['file'],'images/zz-mmtest/docs/') ,'traversal name flattened '.$rn['file']);

// ---- filter
$flt = new MultimediaFilter();
$mk = fn($attrs)=> new SimpleXMLElement('<field '.$attrs.'/>');
$val = json_encode([['file'=>$r['file'],'title'=>'<script>x</script>T','alt'=>'a'],['file'=>$r['file']],['file'=>'images/zz-mmtest/nonexistent.jpg'],['file'=>'configuration.php'],['file'=>$rf['file']]]);
$out = json_decode($flt->filter($mk('mode="gallery" max_files="10"'),$val),true);
ok(count($out)===1 && $out[0]['file']===$r['file'] && $out[0]['title']==='xT','filter gallery: '.json_encode($out));
$out = json_decode($flt->filter($mk('mode="files" max_files="10" file_extensions="pdf,txt"'),json_encode([['file'=>$rf['file']],['file'=>$rt['file']],['file'=>$r['file']]])),true);
ok(count($out)===2,'filter files drops images: '.count($out));
$out = json_decode($flt->filter($mk('mode="files" max_files="1" file_extensions="pdf,txt"'),json_encode([['file'=>$rf['file']],['file'=>$rt['file']]])),true);
ok(count($out)===1,'filter max_files');
ok($flt->filter($mk('mode="gallery"'),'garbage')==='','filter garbage');

// ---- thumbnails
$t = MediaHelper::thumbnail($r['file'],300,200); ok($t && $t['width']===300 && $t['height']===200 && str_contains($t['url'],'multimedia-cache'),'thumb '.json_encode($t));
$t2 = MediaHelper::thumbnail($r['file'],300,200); ok($t2===$t,'thumb cached');
ok(MediaHelper::thumbnail('images/zz-mmtest/nope.jpg',300,200)===null,'thumb missing');
MediaHelper::purgeThumbnails($r['file']); ok(!is_dir(JPATH_ROOT.'/images/multimedia-cache/'.sha1($r['file'])),'thumb purged');

rrm($base); rrm(JPATH_ROOT.'/images/multimedia-cache'); rrm($tmpdir);
echo "\n$n checks, $fail failed\n";
exit($fail ? 1 : 0);
