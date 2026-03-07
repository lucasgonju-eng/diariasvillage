<?php
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Sao_Paulo');
$nowDt = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$nextBusinessDay = static function (DateTimeImmutable $date): DateTimeImmutable {
  $candidate = $date;
  while (in_array((int) $candidate->format('N'), [6, 7], true)) {
    $candidate = $candidate->modify('+1 day');
  }
  return $candidate;
};
$candidateDt = ((int) $nowDt->format('H') >= 16) ? $nowDt->modify('+1 day') : $nowDt;
$minDate = $nextBusinessDay($candidateDt)->format('Y-m-d');
$minDateBr = (DateTimeImmutable::createFromFormat('Y-m-d', $minDate) ?: $nowDt)->format('d/m/Y');
$now = date('c');
?>
<style>
  .m{--primary:#18217b;--gold:#D6B25E;--gold2:#E2C377;--bg:#F4F6FB;--ink:#0B1020;--muted:#556070;--line:#E6E9EF;font-family:Inter,system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;padding:env(safe-area-inset-top,0) 0 env(safe-area-inset-bottom,0)}
  .m *{box-sizing:border-box}
  .m-head{background:linear-gradient(180deg,#0B2B63,#07162F);color:#fff;padding:48px 24px 32px}
  .m-head-inner{max-width:480px;margin:0 auto}
  .m-head h1{margin:0 0 6px;font-size:24px;font-weight:800}
  .m-head p{margin:0;opacity:.8;font-size:14px}
  .m-chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
  .m-chip{font-size:11px;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:#fff}
  .m-body{flex:1;padding:24px 20px 32px;max-width:480px;width:100%;margin:0 auto}
  .m-card{background:#fff;border-radius:18px;padding:24px 20px;box-shadow:0 8px 30px rgba(0,0,0,.08);border:1px solid var(--line);margin-bottom:16px}
  .m-card h2{margin:0 0 6px;font-size:18px;font-weight:800}
  .m-card .sub{margin:0 0 14px;color:var(--muted);font-size:13px}
  .m-countdown{font-size:13px;color:var(--muted);margin-bottom:14px}
  .m-field{margin-bottom:14px}
  .m-field label{display:block;font-size:13px;font-weight:700;color:var(--ink);margin-bottom:6px}
  .m-field input{width:100%;padding:14px 16px;border:1.5px solid var(--line);border-radius:12px;font-size:16px;font-family:inherit;background:#fff;color:var(--ink);transition:border-color .15s}
  .m-field input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(24,33,123,.1)}
  .m-field .hint{font-size:11px;color:var(--muted);margin-top:4px}
  .m-btn{display:block;width:100%;padding:16px;border:none;border-radius:14px;font-size:16px;font-weight:800;font-family:inherit;cursor:pointer;transition:transform .1s;-webkit-tap-highlight-color:transparent;text-align:center}
  .m-btn:active{transform:scale(.97)}
  .m-btn-gold{background:linear-gradient(180deg,var(--gold2),var(--gold));color:#141414;box-shadow:0 8px 24px rgba(214,178,94,.3)}
  .m-info{background:#F3F6FF;border:1px solid #E2E8FF;border-radius:14px;padding:14px 16px;font-size:13px;color:#1f2a44;line-height:1.5}
  .m-info b{font-weight:700}
  .m-rules{margin-top:4px}
  .m-rules li{margin:6px 0;font-size:13px;line-height:1.5}
  .m-topbar{display:flex;justify-content:flex-end;gap:10px;margin-bottom:12px}
  .m-topbar a{font-size:13px;font-weight:700;color:rgba(255,255,255,.8);padding:8px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.06);text-decoration:none}
</style>

<div class="m">
  <div class="m-head">
    <div class="m-head-inner">
      <div class="m-topbar">
        <a href="/mobile/?r=perfil">Perfil</a>
        <a href="/mobile/logout.php">Sair</a>
      </div>
      <h1>Bem-vindo!</h1>
      <p>Escolha a diária do dia com praticidade.</p>
      <div class="m-chips">
        <span class="m-chip">Pagamento via PIX</span>
        <span class="m-chip">Liberação automática</span>
        <span class="m-chip">Confirmação por e-mail</span>
      </div>
    </div>
  </div>

  <div class="m-body">
    <div class="m-card">
      <h2>Montar grade da diária</h2>
      <p class="sub">Escolha a data e siga para a Grade de Oficina Modular.</p>
      <div class="m-countdown" id="m-cd" data-now="<?= htmlspecialchars($now) ?>">Carregando...</div>

      <form id="m-dash">
        <div class="m-field">
          <label>Data</label>
          <input type="text" id="m-date-br" value="<?= htmlspecialchars($minDateBr, ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric" autocomplete="off" placeholder="dd/mm/aaaa" required>
          <input type="hidden" id="m-date" name="date" value="<?= $minDate ?>" data-min-iso="<?= $minDate ?>">
          <div class="hint">Após 16h, somente datas futuras.</div>
        </div>
        <button class="m-btn m-btn-gold" type="submit">Ir para Grade de Oficina Modular</button>
        <div id="m-dash-msg" class="m-msg" style="margin-top:12px;font-size:13px;font-weight:600;min-height:20px;text-align:center;color:var(--err,#b91c1c);display:none"></div>
      </form>
    </div>

    <div class="m-card">
      <h2>Informações</h2>
      <div class="m-info">
        <ul class="m-rules" style="margin:0;padding-left:18px">
          <li><b>Planejada:</b> antes das 10h — R$ 77,00</li>
          <li><b>Emergencial:</b> após 10h — R$ 97,00</li>
          <li><b>Após 16h:</b> só datas futuras</li>
          <li>Pagamento via <b>PIX</b></li>
          <li>Confirmação automática por e-mail</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var el=document.getElementById('m-cd');
  if(el){
    var base=el.dataset.now?new Date(el.dataset.now):new Date();
    function tick(){
      var now=new Date(),t=new Date(base);t.setHours(10,0,0,0);
      var d=t.getTime()-now.getTime();
      if(d<=0){el.textContent='Diária emergencial em vigor a partir das 10h.';return}
      var s=Math.floor(d/1000),h=Math.floor(s/3600),m=Math.floor((s%3600)/60),ss=s%60;
      var p=function(v){return String(v).padStart(2,'0')};
      el.textContent='Diária planejada encerra em '+p(h)+':'+p(m)+':'+p(ss)+'.';
    }
    tick();setInterval(tick,1000);
  }

  var form=document.getElementById('m-dash'),msg=document.getElementById('m-dash-msg');
  var dateBrInput=document.getElementById('m-date-br');
  var dateIsoInput=document.getElementById('m-date');
  function parseBrToIso(raw){
    var m=String(raw||'').trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if(!m)return null;
    var dd=Number(m[1]),mm=Number(m[2]),yy=Number(m[3]);
    var dt=new Date(yy,mm-1,dd);
    if(dt.getFullYear()!==yy||dt.getMonth()!==(mm-1)||dt.getDate()!==dd)return null;
    return String(yy)+'-'+String(mm).padStart(2,'0')+'-'+String(dd).padStart(2,'0');
  }
  function normalizeBrInput(){
    if(!dateBrInput)return;
    var digits=dateBrInput.value.replace(/\D+/g,'').slice(0,8);
    if(digits.length>4) dateBrInput.value=digits.slice(0,2)+'/'+digits.slice(2,4)+'/'+digits.slice(4);
    else if(digits.length>2) dateBrInput.value=digits.slice(0,2)+'/'+digits.slice(2);
    else dateBrInput.value=digits;
  }
  if(dateBrInput){
    dateBrInput.addEventListener('input',normalizeBrInput);
    dateBrInput.addEventListener('blur',function(){
      var iso=parseBrToIso(dateBrInput.value);
      if(!iso)return;
      var p=iso.split('-');
      dateBrInput.value=p[2]+'/'+p[1]+'/'+p[0];
    });
  }
  if(form) form.addEventListener('submit',async function(e){
    e.preventDefault();
    if(msg){msg.style.display='none';msg.textContent='';}
    var dateValBr=dateBrInput?dateBrInput.value.trim():'';
    var dateVal=parseBrToIso(dateValBr);
    var minIso=(dateIsoInput&&dateIsoInput.dataset.minIso)?dateIsoInput.dataset.minIso:'';
    if(!dateVal){if(msg){msg.style.display='block';msg.textContent='Informe a data no formato DD/MM/AAAA.';}return;}
    if(minIso && dateVal < minIso){if(msg){msg.style.display='block';msg.textContent='Selecione a próxima data útil disponível.';}return;}
    if(dateIsoInput) dateIsoInput.value=dateVal;
    var btn=form.querySelector('button[type=submit]');
    btn.disabled=true;btn.textContent='Aguarde...';
    try{
      var res=await fetch('/api/diaria-iniciar.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({date:dateVal})
      });
      var data=await res.json();
      if(!data.ok){if(msg){msg.style.display='block';msg.textContent=data.error||'Não foi possível iniciar a diária.';}btn.disabled=false;btn.textContent='Ir para Grade de Oficina Modular';return;}
      var id=data.diaria_id;
      if(id) window.location.href='/mobile/?r=grade-oficina&diariaId='+encodeURIComponent(id);
      else if(data.redirect_url) window.location.href=data.redirect_url;
      else {btn.disabled=false;btn.textContent='Ir para Grade de Oficina Modular';}
    }catch(err){if(msg){msg.style.display='block';msg.textContent='Erro de conexão. Tente novamente.';}btn.disabled=false;btn.textContent='Ir para Grade de Oficina Modular';}
  });
})();
</script>
