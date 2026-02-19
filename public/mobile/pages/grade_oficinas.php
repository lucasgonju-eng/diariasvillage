<!-- Grade de Oficinas - Diárias Village -->
<style>
  .no-scrollbar::-webkit-scrollbar { display: none; }
  .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<div class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 min-h-screen flex flex-col">
  <!-- Sticky Header -->
  <header class="sticky top-0 z-50 bg-white dark:bg-background-dark border-b border-primary/10 shadow-sm">
    <div class="flex items-center justify-between p-4">
      <div class="flex items-center gap-3">
        <span class="material-symbols-outlined text-primary text-3xl">calendar_today</span>
        <h1 class="text-xl font-bold tracking-tight text-primary">Diárias Village</h1>
      </div>
      <button class="p-2 rounded-full hover:bg-primary/5 transition-colors">
        <span class="material-symbols-outlined text-primary">notifications</span>
      </button>
    </div>
    <!-- Date Selector -->
    <div class="px-4 pb-4">
      <div class="flex gap-2 overflow-x-auto pb-2 no-scrollbar">
        <a class="flex flex-col items-center justify-center min-w-[64px] py-3 rounded-xl border border-transparent bg-white dark:bg-slate-800 shadow-sm" href="#">
          <span class="text-[10px] uppercase font-semibold text-slate-500">Seg</span>
          <span class="text-lg font-bold">23</span>
        </a>
        <a class="flex flex-col items-center justify-center min-w-[64px] py-3 rounded-xl bg-primary text-white shadow-md shadow-primary/20" href="#">
          <span class="text-[10px] uppercase font-semibold opacity-80">Ter</span>
          <span class="text-lg font-bold">24</span>
        </a>
        <a class="flex flex-col items-center justify-center min-w-[64px] py-3 rounded-xl border border-primary/10 bg-white dark:bg-slate-800 shadow-sm" href="#">
          <span class="text-[10px] uppercase font-semibold text-slate-500">Qua</span>
          <span class="text-lg font-bold">25</span>
        </a>
        <a class="flex flex-col items-center justify-center min-w-[64px] py-3 rounded-xl border border-primary/10 bg-white dark:bg-slate-800 shadow-sm" href="#">
          <span class="text-[10px] uppercase font-semibold text-slate-500">Qui</span>
          <span class="text-lg font-bold">26</span>
        </a>
        <a class="flex flex-col items-center justify-center min-w-[64px] py-3 rounded-xl border border-primary/10 bg-white dark:bg-slate-800 shadow-sm" href="#">
          <span class="text-[10px] uppercase font-semibold text-slate-500">Sex</span>
          <span class="text-lg font-bold">27</span>
        </a>
      </div>
    </div>
  </header>

  <!-- Main Schedule Content -->
  <main class="flex-1 overflow-y-auto p-4 space-y-4 max-w-2xl mx-auto w-full">
    <!-- Workshop Card 1 -->
    <div class="bg-white dark:bg-slate-800 rounded-xl overflow-hidden shadow-md border border-primary/5 flex flex-col sm:flex-row">
      <div class="sm:w-32 h-24 sm:h-auto bg-cover bg-center relative" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuBTOWgAO75-SRmLHuKKmHOF8Db_I1BORopOwfbzGOkDfCmHSzk6jviBLq92WZPFAK12vCakvgsSLn23cNdqKR0iPPz6Nxm3gZ53JOlkHaEF-Okuz8TvAvQ1-PaXLTfxsTAiaOM0VLwFST-zB5AQ-ct9WghhcOhOmvHC__PtIZ_nXDYHA3kcwFSKPuhhAsW9d-PRUVIlCGXndkTm1rxi8Xkb08OMLhOopEaY_TZ9K7rJB0YMYBrNmAoFoJ3mGyuTxXoSrgBxyNQ3kwQ')">
        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent sm:hidden"></div>
      </div>
      <div class="p-4 flex-1 flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="material-symbols-outlined text-primary text-sm">schedule</span>
            <p class="text-primary font-bold text-sm">09:00 - 10:00</p>
          </div>
          <h3 class="text-lg font-bold text-slate-900 dark:text-white leading-tight">Yoga Matinal</h3>
          <p class="text-slate-500 text-sm mt-1">Prof. Ana</p>
        </div>
        <div class="flex items-center justify-between mt-4">
          <span class="bg-primary/10 text-primary text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider">Iniciante</span>
          <button data-go="resumo_pedido" class="bg-[#D4AF37] hover:bg-[#c29e2f] text-primary font-bold py-2 px-6 rounded-lg text-sm transition-all transform active:scale-95 shadow-sm">Adicionar</button>
        </div>
      </div>
    </div>

    <!-- Workshop Card 2 -->
    <div class="bg-white dark:bg-slate-800 rounded-xl overflow-hidden shadow-md border border-primary/5 flex flex-col sm:flex-row">
      <div class="sm:w-32 h-24 sm:h-auto bg-cover bg-center relative" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAiu_-q6wabcSgg-tbtuEHLnTCtwU7LQTBBZCbEXjuNSwKA-sWtgfVMIaESvs9vxD-cyNblmTSxQHiJcv5KlkhKGsN4PkGRU4VIuOWSr4G-vxM6ExAtZB8oI8T9uFoSWSUKz8iYvtSjMXqwnjWinw7UgSDUqDgi6ZzY_ZiKeU3xjx7Zv4EUOxWpljeOp-UK4k7wy6tuzTuKl-1Vlcnf8ydCNXPg-RGb85oO8WHxf_FubDj7aFo5tZoWwk6PyzwGRRnb7BkMqcK5C2w')">
        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent sm:hidden"></div>
      </div>
      <div class="p-4 flex-1 flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="material-symbols-outlined text-primary text-sm">schedule</span>
            <p class="text-primary font-bold text-sm">11:00 - 12:30</p>
          </div>
          <h3 class="text-lg font-bold text-slate-900 dark:text-white leading-tight">Pintura em Tela</h3>
          <p class="text-slate-500 text-sm mt-1">Prof. Carlos</p>
        </div>
        <div class="flex items-center justify-between mt-4">
          <span class="bg-primary/10 text-primary text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider">Criatividade</span>
          <button data-go="resumo_pedido" class="bg-[#D4AF37] hover:bg-[#c29e2f] text-primary font-bold py-2 px-6 rounded-lg text-sm transition-all transform active:scale-95 shadow-sm">Adicionar</button>
        </div>
      </div>
    </div>

    <!-- Workshop Card 3 (Destaque) -->
    <div class="bg-white dark:bg-slate-800 rounded-xl overflow-hidden shadow-md border-2 border-primary/20 flex flex-col sm:flex-row relative">
      <div class="absolute top-2 left-2 z-10 bg-primary text-white text-[10px] font-bold px-2 py-1 rounded-md flex items-center gap-1 shadow-lg">
        <span class="material-symbols-outlined text-xs">star</span> DESTAQUE
      </div>
      <div class="sm:w-32 h-24 sm:h-auto bg-cover bg-center relative" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAUAeOH_wemhGmsUb1UOubU08g_IW4E9Uxspj7Rklm9ZMqDxJqPSstind4evqkckxPfeZW1yD0KD1S6gbiO2O4Kpqo7zyvRTMeCoYu3NERmpRagbn60M8mbILDsNKecV21P8dCIhkwmNvOueM-eOJLCgdRsc5DrphFmGEjMfW26lZWmiYPO5NLeT-JOq21hvLvbxjGnKHqhFXlVZU4nJvcgxvBYOCOUCzCk7TBtuaLrTvPvbcoa8lQRy2yujdQ9ikWeCbwm7Md_6p0')">
        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent sm:hidden"></div>
      </div>
      <div class="p-4 flex-1 flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="material-symbols-outlined text-primary text-sm">schedule</span>
            <p class="text-primary font-bold text-sm">14:00 - 15:00</p>
          </div>
          <h3 class="text-lg font-bold text-slate-900 dark:text-white leading-tight">Oficina de Robótica</h3>
          <p class="text-slate-500 text-sm mt-1">Prof. Marcos</p>
        </div>
        <div class="flex items-center justify-between mt-4">
          <span class="bg-primary/10 text-primary text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider">Tecnologia</span>
          <button data-go="resumo_pedido" class="bg-[#D4AF37] hover:bg-[#c29e2f] text-primary font-bold py-2 px-6 rounded-lg text-sm transition-all transform active:scale-95 shadow-sm">Adicionar</button>
        </div>
      </div>
    </div>

    <!-- Workshop Card 4 -->
    <div class="bg-white dark:bg-slate-800 rounded-xl overflow-hidden shadow-md border border-primary/5 flex flex-col sm:flex-row">
      <div class="sm:w-32 h-24 sm:h-auto bg-cover bg-center relative" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDFBouAeNM8oipFwz0YyYEDoSG3kGKphqonJ2GGAUgGJoa8wymNMNlmSsdfkrcd3Siss5KL-ysfsJZuLns4O11UBoxAxDyh0k_EEdngFmabTis_l-9LFc03kEkBiLf_L_xiG0s11x5O6vE9p4UyM5hhJP8J-kphLYjs9sa-RJOSz2QLEwZ8nmi3UddRh2fhsOSlKWQ9IHeO4XDx99SahkuKU95bXa9Pd7OqjIoucVomXKU9pKISbEatWXJgcbdfEIxlzC9cydZwjHo')">
        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent sm:hidden"></div>
      </div>
      <div class="p-4 flex-1 flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="material-symbols-outlined text-primary text-sm">schedule</span>
            <p class="text-primary font-bold text-sm">16:00 - 17:00</p>
          </div>
          <h3 class="text-lg font-bold text-slate-900 dark:text-white leading-tight">Dança Contemporânea</h3>
          <p class="text-slate-500 text-sm mt-1">Prof. Julia</p>
        </div>
        <div class="flex items-center justify-between mt-4">
          <span class="bg-primary/10 text-primary text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider">Movimento</span>
          <button data-go="resumo_pedido" class="bg-[#D4AF37] hover:bg-[#c29e2f] text-primary font-bold py-2 px-6 rounded-lg text-sm transition-all transform active:scale-95 shadow-sm">Adicionar</button>
        </div>
      </div>
    </div>

    <!-- Workshop Card 5 (Esgotado) -->
    <div class="bg-white dark:bg-slate-800 rounded-xl overflow-hidden shadow-md border border-primary/5 flex flex-col sm:flex-row opacity-60 grayscale-[0.5]">
      <div class="sm:w-32 h-24 sm:h-auto bg-cover bg-center relative" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuCVeZcThCeH-wUF3Cvkf4vQRwYD85xLTktqruMhJSgItBR-NgqmUH9vNqiKU_yQV8GY9O6lH9yI7yEgnU5CG5ZOSHkPCG_WD9M-IrzPY3FNTAbGwKYcT3q-Ikq8U9qJArQb6dCIV9HVyNg38toiouKxmkjx9HnEEkd0wneiUjq-uWoSeFvNzfoVC0o0iRPap-lTNn3F0R3ddpnMNDMtyi4yZuPTSjd34unT3Cl6YW1pD7CDym-Nt8fiQqqh4I4rKlrn5xjI0fuSG58')">
        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent sm:hidden"></div>
      </div>
      <div class="p-4 flex-1 flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="material-symbols-outlined text-slate-400 text-sm">schedule</span>
            <p class="text-slate-400 font-bold text-sm">18:00 - 19:30</p>
          </div>
          <h3 class="text-lg font-bold text-slate-900 dark:text-white leading-tight">Confeitaria Básica</h3>
          <p class="text-slate-500 text-sm mt-1">Chef Roberta</p>
        </div>
        <div class="flex items-center justify-between mt-4">
          <span class="bg-slate-100 text-slate-500 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider">Esgotado</span>
          <button class="bg-slate-200 text-slate-500 font-bold py-2 px-6 rounded-lg text-sm cursor-not-allowed" disabled>Lotado</button>
        </div>
      </div>
    </div>

    <div class="h-12"></div>
  </main>

  <!-- Bottom Navigation -->
  <nav class="fixed bottom-0 left-0 right-0 bg-white dark:bg-slate-900 border-t border-primary/10 px-4 py-2 flex items-center justify-around z-50">
    <a data-go="tela_inicial" class="flex flex-col items-center gap-1 text-slate-400 cursor-pointer">
      <span class="material-symbols-outlined">home</span>
      <span class="text-[10px] font-medium">Início</span>
    </a>
    <a class="flex flex-col items-center gap-1 text-primary cursor-pointer">
      <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1">calendar_month</span>
      <span class="text-[10px] font-bold">Agenda</span>
    </a>
    <a class="flex flex-col items-center gap-1 text-slate-400 cursor-pointer">
      <span class="material-symbols-outlined">format_list_bulleted</span>
      <span class="text-[10px] font-medium">Planos</span>
    </a>
    <a class="flex flex-col items-center gap-1 text-slate-400 cursor-pointer">
      <span class="material-symbols-outlined">person</span>
      <span class="text-[10px] font-medium">Perfil</span>
    </a>
  </nav>
</div>
