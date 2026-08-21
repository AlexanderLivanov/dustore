// ============================================================
// STATE.JS — клиентское состояние v3.0 (full-stack)
// ============================================================
// Отличие от демки: articles больше НЕ хардкод. Это кэш того,
// что пришло с сервера. Источник истины теперь — база данных.
// ============================================================

window.S = {
  bg: '#008080',
  user: null,           // { id, username, role, subs_articles, subs_fan, faction }
  settingsOpen: false,
  selSection: 'История',
  activeTab: 'main',
  isFullscreen: false,

  // Кэш с сервера (заполняется в Articles.load / UI.loadSections)
  articles: [],
  sections:    [],
  fanSections: [],

  myRatings: {},        // { articleId: 1-5 } — кэш моих оценок
  searchQuery: '',

  // Звания — две ветки
  ARTICLE_RANKS: [
    { min: 50, name: 'Главный Архивариус', icon: '📜' },
    { min: 20, name: 'Старший Аналитик',   icon: '🔭' },
    { min: 5,  name: 'Полевой Агент',      icon: '🕵' },
    { min: 1,  name: 'Стажёр',             icon: '📋' },
    { min: 0,  name: null,                 icon: ''   },
  ],
  FAN_RANKS: [
    { min: 20, name: 'Мастер Образов', icon: '🎨' },
    { min: 10, name: 'Хронист',        icon: '✒'  },
    { min: 3,  name: 'Летописец',      icon: '📖' },
    { min: 1,  name: 'Дебютант',       icon: '🖊' },
    { min: 0,  name: null,             icon: ''   },
  ],

  FACTIONS: [
    { id: 'none',    name: 'Без фракции', icon: '—',  desc: 'Независимый участник' },
    { id: 'kontrol', name: 'Контроль',    icon: '🔒', desc: 'Сотрудники К.О.Н.Т.У.Р.' },
    { id: 'volk',    name: 'Вольные',     icon: '🌿', desc: 'Независимые исследователи' },
    { id: 'teni',    name: 'Тени',        icon: '👁', desc: 'Информация засекречена' },
  ],
};
