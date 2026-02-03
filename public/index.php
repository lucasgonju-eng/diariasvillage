<?php
require_once __DIR__ . '/../src/Bootstrap.php';

use App\Env;

$baseUrl = rtrim(Env::get('APP_URL', ''), '/');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Diarias Village</title>
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>
  <div class="container">
    <header class="header">
      <div class="logo">Diarias Village</div>
      <nav class="nav">
        <a class="button secondary" href="/login.php">Entrar</a>
        <a class="button" href="/primeiro-acesso.php">Continuar</a>
      </nav>
    </header>

    <section class="hero">
      <div class="hero-grid">
        <div>
          <div class="tag">SaaS oficial do Einstein Village</div>
          <h1 class="title">Diarias simples e seguras para estudantes do 6º ao 8º ano.</h1>
          <p class="subtitle">
            Aqui o responsavel paga a diaria planejada (R$ 77,00 antes das 10h) ou emergencial
            (R$ 97,00 apos as 10h), recebe o codigo de acesso e libera o estudante com rapidez.
          </p>
          <a class="button" href="/primeiro-acesso.php">Continuar</a>
        </div>
        <div class="card">
          <h3>Como funciona</h3>
          <div class="form-group">
            <p class="subtitle">1. Encontre o aluno na lista.</p>
            <p class="subtitle">2. Cadastre o e-mail e confirme.</p>
            <p class="subtitle">3. Escolha a diaria e pague via PIX ou debito.</p>
          </div>
          <div class="notice">Somente estudantes do Colegio Einstein (6º ao 8º ano).</div>
        </div>
      </div>
    </section>
  </div>
</body>
</html>
