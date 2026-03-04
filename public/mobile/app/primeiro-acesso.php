<style>
  .m{--primary:#18217b;--gold:#D6B25E;--gold2:#E2C377;--bg:#F4F6FB;--ink:#0B1020;--muted:#556070;--line:#E6E9EF;--err:#b91c1c;--ok:#15803d;font-family:Inter,system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;padding:env(safe-area-inset-top,0) 0 env(safe-area-inset-bottom,0)}
  .m *{box-sizing:border-box}
  .m-head{background:linear-gradient(180deg,#0B2B63,#07162F);color:#fff;padding:48px 24px 32px;text-align:center}
  .m-head h1{margin:0 0 6px;font-size:24px;font-weight:800}
  .m-head p{margin:0;opacity:.8;font-size:14px}
  .m-body{flex:1;padding:24px 20px 32px;max-width:480px;width:100%;margin:0 auto}
  .m-card{background:#fff;border-radius:18px;padding:24px 20px;box-shadow:0 8px 30px rgba(0,0,0,.08);border:1px solid var(--line)}
  .m-field{margin-bottom:16px}
  .m-field label{display:block;font-size:13px;font-weight:700;color:var(--ink);margin-bottom:6px}
  .m-field input{width:100%;padding:14px 16px;border:1.5px solid var(--line);border-radius:12px;font-size:16px;font-family:inherit;background:#fff;color:var(--ink);transition:border-color .15s}
  .m-field input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(24,33,123,.1)}
  .m-field .hint{font-size:11px;color:var(--muted);margin-top:4px}
  .m-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .m-btn{display:block;width:100%;padding:16px;border:none;border-radius:14px;font-size:16px;font-weight:800;font-family:inherit;cursor:pointer;transition:transform .1s;-webkit-tap-highlight-color:transparent;text-align:center}
  .m-btn:active{transform:scale(.97)}
  .m-btn-gold{background:linear-gradient(180deg,var(--gold2),var(--gold));color:#141414;box-shadow:0 8px 24px rgba(214,178,94,.3)}
  .m-btn-outline{background:#fff;color:var(--primary);border:2px solid var(--line)}
  .m-msg{margin-top:12px;font-size:13px;font-weight:600;min-height:20px;text-align:center}
  .m-msg.error{color:var(--err)}
  .m-msg.success{color:var(--ok)}
  .m-link{display:block;text-align:center;margin-top:20px;font-size:14px;color:var(--muted)}
  .m-link a,.m-link span[data-go]{color:var(--primary);font-weight:700;cursor:pointer}
  .m-pending{display:none;margin-top:16px;padding-top:16px;border-top:1px solid var(--line)}
  .m-pending.show{display:block}
  .m-pending-trigger{font-size:12px;color:var(--muted);margin-top:14px;text-align:center}
  .m-pending-trigger button{background:none;border:none;color:var(--primary);font-weight:700;font-family:inherit;font-size:12px;cursor:pointer;text-decoration:underline}
</style>

<div class="m">
  <div class="m-head">
    <h1>Primeiro acesso</h1>
    <p>CPF, e-mail e senha para criar sua conta</p>
  </div>

  <div class="m-body">
    <div class="m-card">
      <form id="m-reg">
        <div class="m-field">
          <label>CPF do responsável</label>
          <input type="text" id="m-cpf" inputmode="numeric" placeholder="000.000.000-00" autocomplete="off" required>
          <div class="hint">O CPF deve estar no cadastro da escola.</div>
        </div>
        <div class="m-field">
          <label>E-mail</label>
          <input type="email" id="m-email" placeholder="email@exemplo.com" required>
        </div>
        <div class="m-row">
          <div class="m-field">
            <label>Senha</label>
            <input type="password" id="m-pass" required minlength="6">
          </div>
          <div class="m-field">
            <label>Confirmar</label>
            <input type="password" id="m-pass2" required minlength="6">
          </div>
        </div>
        <button class="m-btn m-btn-gold" type="submit">Criar conta</button>
        <div id="m-reg-msg" class="m-msg"></div>
      </form>

      <div class="m-pending-trigger">
        Problemas no cadastro? <button type="button" id="m-open-pend">Enviar pendência</button>
      </div>

      <div class="m-pending" id="m-pend-box">
        <form id="m-pend">
          <div class="m-field">
            <label>Nome do aluno</label>
            <input type="text" id="p-student" required>
          </div>
          <div class="m-field">
            <label>Nome do responsável</label>
            <input type="text" id="p-guardian" required>
          </div>
          <div class="m-field">
            <label>CPF</label>
            <input type="text" id="p-cpf" inputmode="numeric" required>
          </div>
          <div class="m-field">
            <label>E-mail</label>
            <input type="email" id="p-email" required>
          </div>
          <div class="m-field">
            <label>Data do day-use</label>
            <input type="date" id="p-day-use-date" required>
          </div>
          <button class="m-btn m-btn-outline" type="submit">Enviar pendência</button>
          <div id="m-pend-msg" class="m-msg"></div>
        </form>
      </div>
    </div>

    <div class="m-link">
      Já tem conta? <span data-go="tela_login">Fazer login</span>
    </div>
  </div>
</div>

<script>
(function(){
  function mask(v){var d=v.replace(/\D/g,'').slice(0,11),m=d;if(d.length>3)m=d.slice(0,3)+'.'+d.slice(3);if(d.length>6)m=d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6);if(d.length>9)m=d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6,9)+'-'+d.slice(9);return m}
  ['m-cpf','p-cpf'].forEach(function(id){var el=document.getElementById(id);if(el)el.addEventListener('input',function(){this.value=mask(this.value)})});

  var form=document.getElementById('m-reg'),msg=document.getElementById('m-reg-msg');
  if(form)form.addEventListener('submit',async function(e){
    e.preventDefault();msg.textContent='';msg.className='m-msg';
    var btn=form.querySelector('button[type=submit]'),orig=btn.textContent;
    btn.disabled=true;btn.textContent='Criando...';
    try{
      var res=await fetch('/api/register-primeiro-acesso.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
          cpf:document.getElementById('m-cpf').value.trim(),
          email:document.getElementById('m-email').value.trim(),
          password:document.getElementById('m-pass').value,
          password_confirm:document.getElementById('m-pass2').value
        })
      });
      var data;try{data=await res.json()}catch(_){msg.textContent='Resposta inválida.';msg.className='m-msg error';btn.disabled=false;btn.textContent=orig;return}
      if(!data.ok){
        msg.textContent=data.error||'Não foi possível cadastrar.';msg.className='m-msg error';
        if(data.error&&(data.error.toLowerCase().includes('não encontrado')||data.error.toLowerCase().includes('nao encontrado'))){
          document.getElementById('m-pend-box').classList.add('show');
          var pc=document.getElementById('p-cpf');if(pc&&!pc.value)pc.value=document.getElementById('m-cpf').value.trim();
        }
        btn.disabled=false;btn.textContent=orig;return;
      }
      msg.textContent='Conta criada! Faça login com seu CPF e senha.';msg.className='m-msg success';form.reset();
    }catch(err){msg.textContent='Erro de conexão.';msg.className='m-msg error'}
    btn.disabled=false;btn.textContent=orig;
  });

  var openP=document.getElementById('m-open-pend'),pBox=document.getElementById('m-pend-box');
  if(openP)openP.addEventListener('click',function(){pBox.classList.toggle('show')});

  var pForm=document.getElementById('m-pend'),pMsg=document.getElementById('m-pend-msg');
  if(pForm)pForm.addEventListener('submit',async function(e){
    e.preventDefault();pMsg.textContent='';pMsg.className='m-msg';
    try{
      var res=await fetch('/api/pendencia-cadastro.php',{
        method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
          student_name:document.getElementById('p-student').value.trim(),
          guardian_name:document.getElementById('p-guardian').value.trim(),
          guardian_cpf:document.getElementById('p-cpf').value.trim(),
          guardian_email:document.getElementById('p-email').value.trim(),
          payment_date:document.getElementById('p-day-use-date').value
        })
      });
      var data=await res.json();
      if(!data.ok){pMsg.textContent=data.error||'Falha ao enviar.';pMsg.className='m-msg error';return}
      pMsg.textContent='Pendência enviada! Verifique seu e-mail.';pMsg.className='m-msg success';pForm.reset();
    }catch(_){pMsg.textContent='Erro de conexão.';pMsg.className='m-msg error'}
  });
})();
</script>
