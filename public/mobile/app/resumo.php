<style>
  .m{--primary:#18217b;--bg:#F4F6FB;--ink:#0B1020;--muted:#556070;--line:#E6E9EF;font-family:Inter,system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:env(safe-area-inset-top,0) 24px env(safe-area-inset-bottom,0)}
  .m *{box-sizing:border-box}
  .m-card{background:#fff;border-radius:18px;padding:32px 24px;box-shadow:0 8px 30px rgba(0,0,0,.08);border:1px solid var(--line);text-align:center;max-width:400px;width:100%}
  .m-card h2{margin:0 0 8px;font-size:22px;font-weight:800}
  .m-card p{margin:0 0 20px;color:var(--muted);font-size:14px;line-height:1.5}
  .m-btn{display:block;width:100%;padding:16px;border:none;border-radius:14px;font-size:16px;font-weight:800;font-family:inherit;cursor:pointer;transition:transform .1s;-webkit-tap-highlight-color:transparent;text-align:center;background:var(--primary);color:#fff}
  .m-btn:active{transform:scale(.97)}
</style>

<div class="m">
  <div class="m-card">
    <h2>Resumo do pedido</h2>
    <p>O resumo e pagamento são processados diretamente pelo fluxo de oficinas. Volte ao dashboard para iniciar.</p>
    <button class="m-btn" data-go="grade_oficinas">Voltar ao Dashboard</button>
  </div>
</div>
