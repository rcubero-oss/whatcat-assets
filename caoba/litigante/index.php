<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store');
ini_set('session.use_strict_mode','1');
session_name('CAOBALITIGANTE');
session_set_cookie_params(['path'=>'/caoba/litigante','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
require_once __DIR__.'/validation.php';
$fieldErrors=[];
// Configuration is held outside the public web directory. Fail closed until configured.
$configPath=dirname((string)($_SERVER['DOCUMENT_ROOT'] ?? __DIR__)).'/caoba-private/config.php';
$cfg=is_file($configPath) ? require $configPath : [];
if ($cfg) $cfg['storage']=dirname($configPath).'/litigante-storage';
umask(0077);
$configured=true;
foreach (['storage','admin_user','admin_hash','rate_secret'] as $required) {
  if (empty($cfg[$required]) || !is_string($cfg[$required])) $configured=false;
}
$ready=$configured && !empty($cfg['open']) && !empty($cfg['privacy_contact']) && !empty($cfg['retention']);
if (empty($cfg['open']) || isset($_GET['admin'])) header('X-Robots-Tag: noindex, nofollow');
$error=''; $success=false; $db=null;
if ($configured) {
  $root=realpath((string)$_SERVER['DOCUMENT_ROOT']); $storage=realpath($cfg['storage']);
  if (!$storage || !$root || $storage===$root || str_starts_with($storage,$root.DIRECTORY_SEPARATOR)) { http_response_code(503); exit('Configuración de almacenamiento pendiente.'); }
  if (!is_writable($storage)) { http_response_code(503); exit('Almacenamiento temporalmente no disponible.'); }
  $db=new PDO('sqlite:'.$storage.'/applications.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $db->exec('PRAGMA busy_timeout=5000');
  $db->exec('CREATE TABLE IF NOT EXISTS applications (id TEXT PRIMARY KEY, created TEXT, data TEXT, cv TEXT, status TEXT DEFAULT "Recibida", notes TEXT DEFAULT "")');
  $db->exec('CREATE TABLE IF NOT EXISTS limits (key TEXT PRIMARY KEY, until_at INTEGER)');
  // Remove expired personal data on access, including before displaying the panel.
  $cutoff=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-6 months')->format('c');
  $expired=$db->prepare('SELECT id,cv FROM applications WHERE created <= ?');
  $expired->execute([$cutoff]);
  foreach($expired->fetchAll(PDO::FETCH_ASSOC) as $old) {
    $path=$storage.'/'.basename($old['cv']);
    if(is_file($path) && !unlink($path)) { http_response_code(503); exit('Mantenimiento temporal. Intente más tarde.'); }
    $delete=$db->prepare('DELETE FROM applications WHERE id=?'); $delete->execute([$old['id']]);
  }

}
if (isset($_GET['admin'])) {
  $valid=$configured && hash_equals((string)$cfg['admin_user'],(string)($_SERVER['PHP_AUTH_USER']??'')) && password_verify((string)($_SERVER['PHP_AUTH_PW']??''),$cfg['admin_hash']);
  if (!$valid) { header('WWW-Authenticate: Basic realm="Caoba - Litigio"'); http_response_code(401); exit('Acceso restringido.'); }
  if(isset($_GET['cv'])) {
    $stmt=$db->prepare('SELECT cv FROM applications WHERE id=?'); $stmt->execute([$_GET['cv']]); $file=$stmt->fetchColumn();
    if(!$file || !is_file($storage.'/'.basename($file))) { http_response_code(404); exit; }
    header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="curriculum.pdf"'); readfile($storage.'/'.basename($file)); exit;
  }
  if($_SERVER['REQUEST_METHOD']==='POST') {
    if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))) {http_response_code(403);exit;}
    $status=(string)($_POST['status']??'');
    if(in_array($status,['Recibida','En revisión','Preseleccionada','Entrevista','Cerrada'],true)) {
      $s=$db->prepare('UPDATE applications SET status=?, notes=? WHERE id=?'); $s->execute([$status,substr((string)($_POST['notes']??''),0,10000),$_POST['id']??'']);
    }
    header('Location: ?admin=1&application='.rawurlencode((string)($_POST['id']??'' )).'&saved=1');exit;
  }
  $statuses=['Recibida','En revisión','Preseleccionada','Entrevista','Cerrada'];
  $application=(string)($_GET['application']??'');
  $selected=[];
  if($application!=='') {
    $stmt=$db->prepare('SELECT * FROM applications WHERE id=?'); $stmt->execute([$application]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) {http_response_code(404);exit('Candidatura no encontrada.');}
    $selected=[$row];
  }
  echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Candidaturas de Litigio — Caoba</title><link rel="stylesheet" href="../laboral/style.css?v=admin-1"><main class="admin"><h1>Candidaturas de Litigio</h1><p>Registro privado. La evaluación y las decisiones corresponden al equipo de selección.</p>';
  if($application==='') {
    $search=mb_substr(trim((string)($_GET['search']??'')),0,200);
    $filter=(string)($_GET['status']??''); if(!in_array($filter,$statuses,true)) $filter='';
    $matches=[]; $total=(int)$db->query('SELECT COUNT(*) FROM applications')->fetchColumn();
    foreach($db->query('SELECT id,created,data,status FROM applications ORDER BY created DESC,id DESC') as $row) {
      $d=json_decode($row['data'],true)??[];
      $haystack=mb_strtolower(implode(' ',[$d['name']??'',$d['email']??'',$d['profile']??'']));
      if(($filter==='' || $row['status']===$filter) && ($search==='' || mb_strpos($haystack,mb_strtolower($search))!==false)) {
        $row['candidate']=$d; $matches[]=$row;
      }
    }
    $count=count($matches); $pages=max(1,(int)ceil($count/25));
    $page=max(1,min($pages,(int)($_GET['page']??1)));
    echo '<form method="get" class="admin-filters"><input type="hidden" name="admin" value="1"><label>Buscar por nombre, correo o perfil<input name="search" maxlength="200" value="'.h($search).'" type="search"></label><label>Estado<select name="status"><option value="">Todos los estados</option>';
    foreach($statuses as $status) echo '<option '.($filter===$status?'selected':'').'>'.h($status).'</option>';
    echo '</select></label><button>Buscar</button><a href="?admin=1">Limpiar filtros</a></form><p>'.h($count).' de '.h($total).' candidaturas · Más recientes primero</p>';
    if(!$count) echo '<p class="notice">'.($total?'No hay candidaturas que coincidan con los filtros.':'Todavía no hay candidaturas.').'</p>';
    else {
      echo '<div class="admin-table-wrap"><table class="admin-table"><caption class="sr-only">Índice de candidaturas</caption><thead><tr><th scope="col">Nombre</th><th scope="col">Fecha (Costa Rica)</th><th scope="col">Perfil</th><th scope="col">Estado</th><th scope="col">Detalle</th></tr></thead><tbody>';
      foreach(array_slice($matches,($page-1)*25,25) as $row) {
        $d=$row['candidate']; $date=(new DateTimeImmutable($row['created']))->setTimezone(new DateTimeZone('America/Costa_Rica'))->format('d/m/Y H:i');
        echo '<tr><td>'.h($d['name']??'').'</td><td>'.h($date).'</td><td>'.h($d['profile']??'').'</td><td><span class="admin-status">'.h($row['status']).'</span></td><td><a href="?admin=1&amp;application='.h($row['id']).'" aria-label="Ver candidatura de '.h($d['name']??'').'">Ver candidatura</a></td></tr>';
      }
      echo '</tbody></table></div>';
      if($pages>1) {
        echo '<nav class="admin-pages" aria-label="Páginas de candidaturas">';
        foreach(['Anterior'=>$page-1,'Siguiente'=>$page+1] as $label=>$number) if($number>=1 && $number<=$pages) echo '<a href="?'.h(http_build_query(['admin'=>1,'search'=>$search,'status'=>$filter,'page'=>$number])).'">'.h($label).'</a> ';
        echo '<span>Página '.h($page).' de '.h($pages).'</span></nav>';
      }
    }
  } else {
    echo '<p><a href="?admin=1">Volver al índice de candidaturas</a></p>';
    if(isset($_GET['saved'])) echo '<p role="status" class="notice">Revisión guardada.</p>';
  foreach($selected as $row) {
    $d=json_decode($row['data'],true); echo '<article><h2>'.h($d['name']).'</h2><p>'.h($row['created']).' · '.h($row['status']).'</p>';
    foreach($d as $key=>$value) echo '<p><strong>'.h($key).'</strong><br>'.nl2br(h($value)).'</p>';
    echo '<a href="?admin=1&amp;cv='.h($row['id']).'">Descargar currículum PDF</a><form method="post"><input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'"><input type="hidden" name="id" value="'.h($row['id']).'"><label>Estado<select name="status">';
    foreach(['Recibida','En revisión','Preseleccionada','Entrevista','Cerrada'] as $st) echo '<option '.($st===$row['status']?'selected':'').'>'.h($st).'</option>';
    echo '</select></label><label>Observaciones internas<textarea name="notes">'.h($row['notes']).'</textarea></label><button>Guardar revisión</button></form></article>';
  }
  }
  echo '</main></html>';exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && $ready) {
  try {
    if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??'')) || !empty($_POST['website'])) throw new RuntimeException('No se pudo validar el envío. Recargue la página.');
    [$d,$fieldErrors]=validate_application($_POST);
    $f=$_FILES['cv']??[];
    if(($f['error']??1)!==UPLOAD_ERR_OK || ($f['size']??0)>5*1024*1024 || !is_uploaded_file($f['tmp_name']??'')) $fieldErrors['cv']='Adjunte un PDF de hasta 5 MB.';
    elseif((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])!=='application/pdf' || file_get_contents($f['tmp_name'],false,null,0,5)!=='%PDF-') $fieldErrors['cv']='El currículum debe ser un archivo PDF válido.';
    if($fieldErrors) throw new RuntimeException('Revise los campos indicados. Sus datos se conservaron. Vuelva a adjuntar el PDF antes de enviar.');
    $key=hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??''),$cfg['rate_secret']);
    $id=bin2hex(random_bytes(16)); $filename=$id.'.pdf';
    $db->exec('BEGIN IMMEDIATE');
    try {
      $s=$db->prepare('SELECT until_at FROM limits WHERE key=?');$s->execute([$key]);if((int)$s->fetchColumn()>time()) throw new RuntimeException('Espere unos minutos antes de enviar otra postulación.');
      if(!move_uploaded_file($f['tmp_name'],$storage.'/'.$filename)) throw new RuntimeException('No se pudo guardar el archivo. Intente de nuevo.');chmod($storage.'/'.$filename,0600);
      $s=$db->prepare('INSERT INTO applications(id,created,data,cv) VALUES(?,?,?,?)');$s->execute([$id,gmdate('c'),json_encode($d,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$filename]);
      $db->exec('DELETE FROM limits WHERE until_at < '.time());
      $s=$db->prepare('INSERT OR REPLACE INTO limits VALUES(?,?)');$s->execute([$key,time()+300]);$db->exec('COMMIT');
    } catch(Throwable $e) {$db->exec('ROLLBACK');if(is_file($storage.'/'.$filename))unlink($storage.'/'.$filename);throw $e;}
    $_SESSION['csrf']=bin2hex(random_bytes(32));
    $_SESSION['receipt']=$id;header('Location: ?enviada=1');exit;
  } catch(PDOException $e) {error_log($e->getMessage());$error='No pudimos guardar su postulación. Intente nuevamente más tarde.';} catch(RuntimeException $e) {$error=$e->getMessage();} catch(Throwable $e) {$error='No pudimos guardar su postulación. Intente nuevamente más tarde.';}
}
$success=isset($_GET['enviada'],$_SESSION['receipt']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vacante de abogado/a litigante | Caoba Legal</title>
  <meta name="description" content="Vacante para abogado/a litigante con experiencia en litigios civiles y comerciales en Caoba Legal.">
  <link rel="stylesheet" href="style.css?v=1">
</head>
<body>
<header class="site-header">
  <div class="wrap header-inner">
    <a href="https://www.caobalegal.com/"><img src="../laboral/brand/caoba-legal.png" alt="Caoba Legal" width="500" height="154"></a>
    <span>Forme parte del equipo</span>
  </div>
</header>
<main>
  <section class="hero" aria-labelledby="hero-title">
    <div class="wrap hero-inner">
      <p class="badge">Vacante disponible · Abogado/a litigante</p>
      <h1 id="hero-title">Buscamos una persona con experiencia en litigios civiles y comerciales para integrarse al equipo.</h1>
      <a class="button" href="#postulacion">Postularme</a>
    </div>
  </section>
  <section class="wrap requirements" aria-labelledby="requirements-title">
    <h2 id="requirements-title">Lo que buscamos</h2>
    <ul class="requirements-grid">
      <li><span class="eyebrow">Experiencia requerida</span><strong>Mínimo 5 años</strong><p>De experiencia en litigios civiles y comerciales.</p></li>
      <li><span class="eyebrow">Requisito</span><strong>Incorporación al Colegio</strong><p>Colegio de Abogados y Abogadas de Costa Rica.</p></li>
      <li><span class="eyebrow">Deseable</span><strong>Derecho Penal</strong><p>Se valorará la experiencia en esta área.</p></li>
    </ul>
  </section>
  <section class="application" id="postulacion" aria-labelledby="application-title">
    <div class="wrap application-inner">
      <div>
        <p class="eyebrow">Su siguiente paso</p>
        <h2 id="application-title">¿Cumple con el perfil? Intégrese a nuestro equipo.</h2>
        <p>Complete sus datos básicos y adjunte su currículum en PDF, de hasta 5 MB.</p>
      </div>
      <div class="form-card">
        <?php if($success): ?>
          <div role="status"><h3>Recibimos su postulación.</h3><p>Gracias por su interés en formar parte de Caoba Legal.</p><p class="hint">Referencia: <?=h($_SESSION['receipt'])?></p></div>
        <?php elseif(!$ready): ?>
          <p>Las postulaciones se habilitarán en esta página.</p>
        <?php else: ?>
          <?php if($error): ?>
            <div role="alert" class="error-summary" tabindex="-1" autofocus>
              <p><?=h($error)?></p>
              <?php if($fieldErrors): ?><ul><?php foreach($fieldErrors as $field=>$message): ?>
                <li><a href="#field-<?=h($field)?>"><?=h($message)?></a></li>
              <?php endforeach; ?></ul><?php endif; ?>
            </div>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data" action="./#postulacion">
            <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
            <div class="trap" aria-hidden="true"><label>Sitio web<input name="website" tabindex="-1" autocomplete="off"></label></div>
            <?php
            $fields=[
              'name'=>['Nombre completo','text','name',200,true],
              'email'=>['Correo electrónico','email','email',200,true],
              'phone'=>['Teléfono','tel','tel',200,true],
              'registration'=>['Número de incorporación','text','off',50,true],
              'linkedin'=>['Perfil de LinkedIn (opcional)','url','url',500,false],
              'cv'=>['Currículum en PDF','file','off',0,true],
            ];
            foreach($fields as $key=>[$label,$type,$autocomplete,$limit,$required]):
              $value=is_string($_POST[$key]??null)?$_POST[$key]:'';
              $fieldError=$fieldErrors[$key]??'';
              $hint=match($key){'cv'=>'PDF de hasta 5 MB.','linkedin'=>'Copie el enlace desde su perfil.',default=>''};
            ?>
              <div class="field">
                <label for="field-<?=h($key)?>"><?=h($label)?></label>
                <input id="field-<?=h($key)?>" name="<?=h($key)?>" type="<?=h($type)?>" autocomplete="<?=h($autocomplete)?>"
                  <?=$required?'required':''?> <?=$limit?'maxlength="'.h($limit).'"':''?>
                  <?=$type==='file'?'accept="application/pdf,.pdf"':'value="'.h($value).'"'?>
                  <?=$fieldError?'aria-invalid="true" aria-describedby="error-'.h($key).'"':($hint?'aria-describedby="hint-'.h($key).'"':'')?>>
                <?php if($hint): ?><small class="hint" id="hint-<?=h($key)?>"><?=h($hint)?></small><?php endif; ?>
                <?php if($fieldError): ?><small class="field-error" id="error-<?=h($key)?>"><?=h($fieldError)?></small><?php endif; ?>
              </div>
            <?php endforeach; ?>
            <p class="privacy">Caoba Legal utilizará sus datos y currículum exclusivamente para este proceso de selección. Tendrán acceso las personas autorizadas del equipo de selección y de soporte técnico de WhatCat. Contacto: <?=h($cfg['privacy_contact'])?>. Conservación: <?=h($cfg['retention'])?>.</p>
            <label class="check"><input id="field-consent" required type="checkbox" name="consent" value="1" <?=($_POST['consent']??null)==='1'?'checked':''?> <?=isset($fieldErrors['consent'])?'aria-invalid="true" aria-describedby="error-consent"':''?>> <span>Autorizo el tratamiento de mis datos para esta postulación.</span></label>
            <?php if(isset($fieldErrors['consent'])): ?><small class="field-error" id="error-consent"><?=h($fieldErrors['consent'])?></small><?php endif; ?>
            <button type="submit">Enviar mi postulación</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>
<footer class="site-footer"><div class="wrap header-inner"><a href="https://www.caobalegal.com/"><img src="../laboral/brand/caoba-legal.png" alt="Caoba Legal" width="500" height="154"></a><span>Litigio civil y comercial</span></div></footer>
</body>
</html>
