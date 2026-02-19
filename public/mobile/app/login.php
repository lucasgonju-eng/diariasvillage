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
  .m-btn{display:block;width:100%;padding:16px;border:none;border-radius:14px;font-size:16px;font-weight:800;font-family:inherit;cursor:pointer;transition:transform .1s;-webkit-tap-highlight-color:transparent;text-align:center}
  .m-btn:active{transform:scale(.97)}
  .m-btn-gold{background:linear-gradient(180deg,var(--gold2),var(--gold));color:#141414;box-shadow:0 8px 24px rgba(214,178,94,.3)}
  .m-msg{margin-top:12px;font-size:13px;font-weight:600;min-height:20px;text-align:center}
  .m-msg.error{color:var(--err)}
  .m-msg.success{color:var(--ok)}
  .m-link{display:block;text-align:center;margin-top:20px;font-size:14px;color:var(--muted)}
  .m-link a,.m-link span[data-go]{color:var(--primary);font-weight:700;cursor:pointer}
</style>

<div class="m">
  <div class="m-head">
    <h1>Entrar</h1>
    <p>Acesse com o CPF do responsável</p>
  </div>

  <div class="m-body">
    <div class="m-card">
      <form id="m-login">
        <div class="m-field">
          <label>CPF do responsável</label>
          <input type="text" id="m-cpf" inputmode="numeric" placeholder="000.000.000-00" autocomplete="off" required>
        </div>
        <div class="m-field">
          <label>Senha</label>
          <input type="password" id="m-pass" placeholder="••••••••" required>
        </div>
        <button class="m-btn m-btn-gold" type="submit">Entrar</button>
        <div id="m-login-msg" class="m-msg"></div>
      </form>
    </div>

    <div class="m-link">
      Primeiro acesso? <span data-go="tela_cadastro">Criar conta</span>
    </div>
  </div>
</div>

<script>
(function(){
  var cpf=document.getElementById('m-cpf');
  if(cpf) cpf.addEventListener('input',function(){
    var d=this.value.replace(/\D/g,'').slice(0,11),m=d;
    if(d.length>3)m=d.slice(0,3)+'.'+d.slice(3);
    if(d.length>6)m=d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6);
    if(d.length>9)m=d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6,9)+'-'+d.slice(9);
    this.value=m;
  });

  var form=document.getElementById('m-login');
  var msg=document.getElementById('m-login-msg');
  if(form) form.addEventListener('submit',async function(e){
    e.preventDefault();
    msg.textContent='';msg.className='m-msg';
    var btn=form.querySelector('button[type=submit]');
    btn.disabled=true;btn.textContent='Entrando...';
    try{
      var res=await fetch('/api/login.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
          cpf:document.getElementById('m-cpf').value.trim(),
          password:document.getElementById('m-pass').value
        })
      });
      var data=await res.json();
      if(!data.ok){
        msg.textContent=data.error||'Falha ao entrar.';
        msg.className='m-msg error';
        btn.disabled=false;btn.textContent='Entrar';
        return;
      }
      window.location.href='/mobile/?r=grade';
    }catch(err){
      msg.textContent='Erro de conexão. Tente novamente.';
      msg.className='m-msg error';
      btn.disabled=false;btn.textContent='Entrar';
    }
  });
})();
</script>
