<?php
declare(strict_types=1);

function validate_application(array $post): array {
  $data=['profile'=>'Abogado/a litigante'];
  $errors=[];
  $value=static fn(string $key): string => is_string($post[$key]??null)?trim($post[$key]):'';
  $labels=[
    'name'=>'su nombre completo',
    'email'=>'su correo electrónico',
    'phone'=>'su teléfono',
    'registration'=>'su número de incorporación',
  ];
  foreach($labels as $field=>$label) {
    $data[$field]=$value($field);
    if($data[$field]==='') {
      $errors[$field]='Ingrese '.$label.'.';
    } elseif(mb_strlen($data[$field])>($field==='registration'?50:200)) {
      $errors[$field]='El texto de este campo es demasiado extenso.';
    }
  }
  if($data['email']!=='' && !filter_var($data['email'],FILTER_VALIDATE_EMAIL)) {
    $errors['email']='Ingrese un correo electrónico válido.';
  }
  $data['Número de incorporación']=$data['registration'];
  unset($data['registration']);
  $linkedin=$value('linkedin');
  if($linkedin!=='') {
    // Encode Unicode characters without removing accents or double-encoding % escapes.
    $encoded=preg_replace_callback('/[^\x21-\x7e]/u',static fn($m)=>rawurlencode($m[0]),$linkedin);
    $url=is_string($encoded)?parse_url($encoded):false;$host=strtolower((string)($url['host']??''));
    if(mb_strlen($linkedin)>500 || preg_match('/\s/u',$linkedin) || preg_match('/%(?![0-9a-f]{2})/i',$linkedin) || !$encoded || !filter_var($encoded,FILTER_VALIDATE_URL) || ($url['scheme']??'')!=='https' || !($host==='linkedin.com' || str_ends_with($host,'.linkedin.com')) || !preg_match('~^/in/[^/]+/?$~',$url['path']??'') || isset($url['user']) || isset($url['pass']) || isset($url['port'])) $errors['linkedin']='Copie el enlace completo de su perfil de LinkedIn (https://www.linkedin.com/in/…). También puede dejar este campo vacío.';
    else $data['Perfil de LinkedIn']=$encoded;
  }
  if($value('consent')!=='1') $errors['consent']='Confirme la autorización para procesar su postulación.';
  $data['consent']='Autorizado para este proceso de selección';$data['consent_at']=gmdate('c');
  return [$data,$errors];
}
