<!-- Tela Inicial - Diárias Village -->
<div class="relative flex h-full min-h-screen w-full flex-col bg-background-light dark:bg-background-dark group/design-root overflow-x-hidden">

  <!-- Hero Section -->
  <div class="flex-1 flex flex-col justify-center items-center px-6 w-full max-w-[480px] mx-auto">

    <!-- Logo / Branding -->
    <div class="mb-10 flex flex-col items-center">
      <div class="w-24 h-24 bg-primary/10 dark:bg-primary/20 rounded-3xl flex items-center justify-center mb-8 shadow-lg shadow-primary/10">
        <span class="material-symbols-outlined text-primary text-6xl">holiday_village</span>
      </div>
      <h1 class="text-primary text-[36px] font-bold leading-tight text-center tracking-tight">
        Diárias Village
      </h1>
      <p class="text-slate-500 dark:text-slate-400 text-base font-normal leading-normal mt-3 text-center max-w-[320px]">
        Workshops, oficinas e experiências incríveis para toda a família.
      </p>
    </div>

    <!-- CTA Buttons -->
    <div class="w-full space-y-4 mt-4">
      <button data-go="tela_login"
        class="w-full bg-primary hover:bg-primary/90 text-white font-bold h-14 rounded-xl flex items-center justify-center shadow-lg shadow-primary/20 active:scale-[0.98] transition-all">
        <span class="material-symbols-outlined mr-2">login</span>
        <span>Entrar na minha conta</span>
      </button>

      <button data-go="tela_cadastro"
        class="w-full bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-primary font-bold h-14 rounded-xl flex items-center justify-center border-2 border-primary/20 active:scale-[0.98] transition-all">
        <span class="material-symbols-outlined mr-2">person_add</span>
        <span>Criar conta</span>
      </button>
    </div>

    <!-- Feature Pills -->
    <div class="flex flex-wrap justify-center gap-2 mt-10">
      <span class="bg-primary/5 text-primary text-xs font-semibold px-3 py-1.5 rounded-full flex items-center gap-1">
        <span class="material-symbols-outlined text-sm">palette</span> Oficinas
      </span>
      <span class="bg-primary/5 text-primary text-xs font-semibold px-3 py-1.5 rounded-full flex items-center gap-1">
        <span class="material-symbols-outlined text-sm">sports_martial_arts</span> Esportes
      </span>
      <span class="bg-primary/5 text-primary text-xs font-semibold px-3 py-1.5 rounded-full flex items-center gap-1">
        <span class="material-symbols-outlined text-sm">music_note</span> Música
      </span>
      <span class="bg-primary/5 text-primary text-xs font-semibold px-3 py-1.5 rounded-full flex items-center gap-1">
        <span class="material-symbols-outlined text-sm">smart_toy</span> Tech
      </span>
    </div>
  </div>

  <!-- Footer -->
  <div class="pb-8 pt-6 text-center">
    <div class="flex items-center justify-center gap-2 text-slate-400 text-xs">
      <span class="material-symbols-outlined text-sm">verified_user</span>
      <span>Ambiente seguro e criptografado</span>
    </div>
    <div class="mt-4 flex justify-center gap-1 opacity-30">
      <div class="size-2 rounded-full bg-primary"></div>
      <div class="size-2 rounded-full bg-primary"></div>
      <div class="size-2 rounded-full bg-primary/40"></div>
    </div>
  </div>
</div>
