<?php
declare(strict_types=1);
function validate_application(array $post, array $questions): array {
  $data=[]; $errors=[];
  $value=static fn(string $key): string => is_string($post[$key]??null)?trim($post[$key]):'';
  $labels=['name'=>'su nombre completo','email'=>'su correo electrónico','phone'=>'su teléfono','education'=>'su formación','availability'=>'su fecha de incorporación','profile'=>'su perfil'];
  foreach($labels as $key=>$label) {
    $data[$key]=$value($key);
    if($data[$key]==='') $errors[$key]='Complete '.$label.'.';
    elseif(mb_strlen($data[$key])>(in_array($key,['name','email','phone'],true)?200:2000)) $errors[$key]='El texto de este campo es demasiado extenso.';
  }
  if($data['email']!=='' && !filter_var($data['email'],FILTER_VALIDATE_EMAIL)) $errors['email']='Ingrese un correo válido. Este formulario admite direcciones sin tildes ni ñ; use otra dirección si la suya las contiene. No modifique su dirección existente.';
  $date=DateTimeImmutable::createFromFormat('!Y-m-d',$data['availability']);
  if(!$date || $date->format('Y-m-d')!==$data['availability']) $errors['availability']='Indique una fecha válida de incorporación.';
  if(!in_array($data['profile'],['Abogado/a incorporado/a','Asistente estudiante de Derecho'],true)) $errors['profile']='Seleccione uno de los perfiles disponibles.';
  $travel=$value('travel');
  if(!in_array($travel,['Sí','No'],true)) $errors['travel']='Indique si tiene disponibilidad para movilizarse para atender casos.';
  $data['Disponibilidad para movilizarse para atender casos']=$travel;
  foreach($questions as $i=>$question) {
    $answer=$value('q'.$i);$count=$answer===''?0:count(preg_split('/\s+/u',$answer));
    if(!$count) $errors['q'.$i]='Responda la pregunta '.($i+1).'.';
    elseif($count>100 || mb_strlen($answer)>4000) $errors['q'.$i]='La pregunta '.($i+1).' tiene '.$count.' palabras. El máximo es 100.';
    $data[$question]=$answer;
  }
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
