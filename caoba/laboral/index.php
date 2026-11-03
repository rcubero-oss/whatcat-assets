<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store');
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$questions=[
'¿Qué le interesa específicamente del derecho laboral enfocado en empresas y por qué quiere desarrollarse en esta área?',
'Cuéntenos una ocasión en la que recibió una corrección sobre su trabajo o un proyecto académico. ¿Cómo respondió y qué cambió?',
'Un cliente empresarial envía una consulta urgente y faltan datos importantes. ¿Qué haría antes de preparar una respuesta?',
'Un cliente le pide incluir una cláusula en un contrato laboral que considera inválida o riesgosa. ¿Cómo abordaría la situación?',
'Tiene dos consultas urgentes de distintos clientes corporativos para hoy. ¿Qué criterios usaría para priorizarlas?',
'Cuéntenos una ocasión en la que tuvo que entregar un resultado de alta calidad bajo presión de tiempo. ¿Cómo se organizó?'];
// Configuration is held outside the public web directory. Fail closed until configured.
$configPath=dirname((string)($_SERVER['DOCUMENT_ROOT'] ?? __DIR__)).'/caoba-private/config.php';
$cfg=is_file($configPath) ? require $configPath : [];
umask(0077);
$configured=true;
foreach (['storage','admin_user','admin_hash','rate_secret'] as $required) {
  if (empty($cfg[$required]) || !is_string($cfg[$required])) $configured=false;
}
if (isset($_GET['preview']) && $configured && !empty($cfg['preview_secret']) && hash_equals($cfg['preview_secret'], (string)$_GET['preview'])) {
  $_SESSION['preview']=true;
  header('Location: ./'); exit;
}
$ready=$configured && (!empty($cfg['open']) || (!empty($cfg['preview_secret']) && !empty($_SESSION['preview']))) && !empty($cfg['privacy_contact']) && !empty($cfg['retention']);
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
  if (!$valid) { header('WWW-Authenticate: Basic realm="Caoba - Seleccion"'); http_response_code(401); exit('Acceso restringido.'); }
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
    header('Location: ?admin=1');exit;
  }
  echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Candidaturas — Caoba</title><link rel="stylesheet" href="style.css?v=caoba-3"><main><h1>Candidaturas</h1><p>Registro privado. La evaluación y las decisiones corresponden al equipo de selección.</p>';
  foreach($db->query('SELECT * FROM applications ORDER BY created DESC') as $row) {
    $d=json_decode($row['data'],true); echo '<article><h2>'.h($d['name']).'</h2><p>'.h($row['created']).' · '.h($row['status']).'</p>';
    foreach($d as $key=>$value) echo '<p><strong>'.h($key).'</strong><br>'.nl2br(h($value)).'</p>';
    echo '<a href="?admin=1&amp;cv='.h($row['id']).'">Descargar currículum PDF</a><form method="post"><input type="hidden" name="csrf" value="'.h($_SESSION['csrf']).'"><input type="hidden" name="id" value="'.h($row['id']).'"><label>Estado<select name="status">';
    foreach(['Recibida','En revisión','Preseleccionada','Entrevista','Cerrada'] as $st) echo '<option '.($st===$row['status']?'selected':'').'>'.h($st).'</option>';
    echo '</select></label><label>Observaciones internas<textarea name="notes">'.h($row['notes']).'</textarea></label><button>Guardar revisión</button></form></article>';
  } echo '</main>';exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && $ready) {
  try {
    if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??'')) || !empty($_POST['website'])) throw new RuntimeException('No se pudo validar el envío. Recargue la página.');
    if(empty($_POST['consent'])) throw new RuntimeException('Confirme la autorización para procesar su postulación.');
    $d=['consent'=>'Autorizado para este proceso de selección', 'consent_at'=>gmdate('c')];
    foreach(['name','email','phone','profile','education','availability'] as $field) { $d[$field]=trim((string)($_POST[$field]??'')); if(!$d[$field] || strlen($d[$field])>2000) throw new RuntimeException('Complete los datos solicitados.'); }
    if(!filter_var($d['email'],FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Revise su correo electrónico.');
    foreach(['name','email','phone'] as $field) if(mb_strlen($d[$field])>200) throw new RuntimeException('Revise la extensión de sus datos de contacto.');
    if(!in_array($d['profile'],['Abogado/a junior incorporado/a','Asistente senior estudiante de Derecho'],true)) throw new RuntimeException('Seleccione el perfil.');
    foreach($questions as $i=>$q) { $a=trim((string)($_POST['q'.$i]??'')); if(!$a || count(preg_split('/\s+/u',$a))>100 || strlen($a)>4000) throw new RuntimeException('Responda cada pregunta en un máximo de 100 palabras.'); $d[$q]=$a; }
    $f=$_FILES['cv']??[];
    if(($f['error']??1)!==UPLOAD_ERR_OK || ($f['size']??0)>5*1024*1024 || !is_uploaded_file($f['tmp_name'])) throw new RuntimeException('Adjunte un PDF de hasta 5 MB.');
    if((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])!=='application/pdf' || file_get_contents($f['tmp_name'],false,null,0,5)!=='%PDF-') throw new RuntimeException('El currículum debe ser un archivo PDF válido.');
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
  } catch(RuntimeException $e) {$error=$e->getMessage();} catch(Throwable $e) {$error='No pudimos guardar su postulación. Intente nuevamente más tarde.';}
}
$success=isset($_GET['enviada'],$_SESSION['receipt']);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Construya su carrera en Derecho Laboral Corporativo | Caoba Legal</title><meta name="description" content="Una oportunidad para convertir su ambición en criterio. Asistente senior o abogado/a junior en Caoba Legal."><link rel="stylesheet" href="style.css?v=caoba-3"></head><body>
<header><a href="#" class="brand" aria-label="Caoba Legal, inicio"><img src="brand/caoba-10anos.png" alt="Caoba Legal · 10 años" width="360" height="203"></a><span>Forme parte del equipo</span></header>
<main><section class="hero"><p class="eyebrow">DERECHO LABORAL CORPORATIVO</p><h1>Una oportunidad para convertir su ambición en <em>criterio.</em></h1><p class="lead">Construya una trayectoria sólida junto a Paola Gutiérrez y Juan José Carreras, desarrollando experiencia en la atención a empresas.</p><a class="button" href="#postulacion">Conocer la oportunidad <span>↓</span></a><p class="caption">Una vacante · Asistente senior o abogado/a junior</p></section>
<section class="intro"><p class="eyebrow">LA OPORTUNIDAD</p><h2>El criterio se desarrolla en la práctica, con la guía adecuada.</h2><p>Buscamos una persona motivada y con excelente actitud, interesada en especializarse en derecho laboral corporativo. Una oportunidad de aprendizaje cercano, corrección constructiva y experiencia con clientes empresariales destacados.</p></section>
<section><p class="eyebrow">LO QUE BUSCAMOS</p><div class="grid"><article><b>01 / Aprendizaje</b><h3>Ambición con apertura.</h3><p>Disposición para aprender, recibir correcciones y asumir responsabilidades.</p></article><article><b>02 / Rigor</b><h3>Atención a los detalles.</h3><p>Excelente redacción, seguimiento de instrucciones y dedicación al trabajo.</p></article><article><b>03 / Profesionalismo</b><h3>Criterio en cada interacción.</h3><p>Presentación y trato profesionales y capacidad para trasladarse cuando las funciones lo requieran.</p></article></div><p><strong>Una sola plaza, dos perfiles posibles:</strong> abogado/a junior incorporado/a al Colegio de Abogados o asistente senior estudiante de Derecho.</p></section>
<section id="postulacion"><p class="eyebrow">SU SIGUIENTE PASO</p><h2>Cuéntenos cómo quiere crecer.</h2>
<?php if($success): ?><div role="status" class="notice"><h3>Recibimos su postulación.</h3><p>Su currículum y respuestas quedaron registrados para revisión del equipo de selección.</p><p>Referencia: <?=h($_SESSION['receipt'])?></p></div>
<?php elseif(!$ready): ?><div class="notice"><h3>Próximamente abriremos las postulaciones.</h3><p>Estamos preparando los detalles de la oportunidad y el proceso de selección. El formulario se habilitará en esta misma página.</p></div>
<?php else: ?><p>Complete las seis respuestas en un máximo de 100 palabras cada una. Puede utilizar experiencias laborales o académicas. Adjunte su currículum en PDF, de hasta 5 MB.</p>
<?php if($error): ?><p role="alert" class="notice"><?=h($error)?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><div class="trap" aria-hidden="true"><label>Sitio web<input name="website" tabindex="-1" autocomplete="off"></label></div>
<?php foreach(['name'=>'Nombre completo','email'=>'Correo electrónico','phone'=>'Teléfono'] as $key=>$label): ?><label><?=h($label)?><input required name="<?=$key?>" type="<?=$key==='email'?'email':($key==='phone'?'tel':'text')?>" maxlength="200" value="<?=h($_POST[$key]??'')?>"></label><?php endforeach; ?>
<label>Perfil<select required name="profile"><option value="">Seleccione</option><?php foreach(['Abogado/a junior incorporado/a','Asistente senior estudiante de Derecho'] as $profile): ?><option <?=($_POST['profile']??'')===$profile?'selected':''?>><?=h($profile)?></option><?php endforeach; ?></select></label>
<label>Formación, universidad y avance de carrera o número de incorporación<textarea required name="education" maxlength="2000"><?=h($_POST['education']??'')?></textarea></label><label>Disponibilidad de ingreso y para traslados relacionados con el puesto<textarea required name="availability" maxlength="2000"><?=h($_POST['availability']??'')?></textarea></label>
<?php foreach($questions as $i=>$q): ?><label><span class="eyebrow">PREGUNTA <?=$i+1?> / 6</span><br><?=h($q)?><textarea required name="q<?=$i?>" maxlength="4000"><?=h($_POST['q'.$i]??'')?></textarea><small>Máximo 100 palabras.</small></label><?php endforeach; ?>
<label>Currículum en PDF · máximo 5 MB<input required type="file" name="cv" accept="application/pdf,.pdf"></label><p>Caoba Legal utilizará sus datos, respuestas y currículum exclusivamente para gestionar este proceso de selección. Tendrán acceso las personas autorizadas del equipo de selección y de soporte técnico de WhatCat. Para consultas sobre sus datos: <?=h($cfg['privacy_contact'])?>. Conservación: <?=h($cfg['retention'])?>.</p><label class="check"><input required type="checkbox" name="consent" value="1"> He leído la información del proceso y autorizo el tratamiento de mis datos para esta postulación.</label><button type="submit">Enviar mi postulación ↗</button></form><?php endif; ?></section>
</main><footer><span class="brand"><img src="brand/caoba-10anos.png" alt="Caoba Legal · 10 años" width="360" height="203"></span><p>Derecho Laboral Corporativo</p></footer></body></html>
