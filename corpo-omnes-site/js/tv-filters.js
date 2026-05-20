// Filtres TV - filtre par campus (?campus=Citroen|Citadelle) et par école (?ecole=ECE|ESCE|...)
(function (global) {
  'use strict';

  const CAMPUS_ECOLES = {
    Citroen: ['ESCE', 'INSEEC Bachelor', 'INSEEC BBA', 'INSEEC BTS', 'INSEEC GE', 'INSEEC MSc'],
    Citadelle: ['ECE', 'HEIP', 'Sup de Pub'],
  };

  const CAMPUS_LABEL = {
    Citroen: 'Campus Citroën',
    Citadelle: 'Campus Citadelle',
  };

  const CAMPUS_INVITE_LABEL = {
    Citroen: 'Citroën',
    Citadelle: 'Citadelle',
  };

  // les programmes INSEEC se regroupent sous un seul filtre "inseec"
  const INSEEC_PROGRAMS = [
    'INSEEC Bachelor',
    'INSEEC BBA',
    'INSEEC BTS',
    'INSEEC GE',
    'INSEEC MSc',
  ];

  // couleurs par école (doivent correspondre au style.css)
  const ECOLE_COLORS = {
    ECE: '#007179',
    ESCE: '#002D74',
    HEIP: '#E52521',
    INSEEC: '#003DA5',
    'INSEEC Bachelor': '#003DA5',
    'INSEEC BBA': '#003DA5',
    'INSEEC BTS': '#003DA5',
    'INSEEC GE': '#003DA5',
    'INSEEC MSc': '#003DA5',
    'Sup de Pub': '#FF5B05',
  };

  const ECOLE_ALIASES = {
    ece: 'ECE',
    esce: 'ESCE',
    heip: 'HEIP',
    inseec: 'INSEEC',
    'sup de pub': 'Sup de Pub',
    supdepub: 'Sup de Pub',
    'sup-de-pub': 'Sup de Pub',
  };

  const TYPE_COLORS = {
    Corpo: '#C45EFF',
    BDE: '#9F9EB7',
    Sport: '#3ECF8E',
    RSE: '#5bd6a0',
    Association: '#f87171',
  };

  function fold(s) {
    return String(s || '')
      .trim()
      .toLowerCase()
      .normalize('NFD')
      .replace(/\p{M}/gu, '');
  }

  function hexToRgb(hex) {
    const h = String(hex || '').replace('#', '').trim();
    if (h.length === 3) {
      return {
        r: parseInt(h[0] + h[0], 16),
        g: parseInt(h[1] + h[1], 16),
        b: parseInt(h[2] + h[2], 16),
      };
    }
    if (h.length !== 6) return null;
    return {
      r: parseInt(h.slice(0, 2), 16),
      g: parseInt(h.slice(2, 4), 16),
      b: parseInt(h.slice(4, 6), 16),
    };
  }

  function hexToRgba(hex, alpha) {
    const rgb = hexToRgb(hex);
    if (!rgb) return `rgba(93, 2, 130, ${alpha})`;
    return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`;
  }

  // normalise une couleur hex (#abc ou abc123 → #AABBCC)
  function normalizeHexColor(raw) {
    if (raw == null || raw === '') return null;
    let c = String(raw).trim();
    if (/^[0-9A-Fa-f]{3}$/.test(c)) {
      c = '#' + c[0] + c[0] + c[1] + c[1] + c[2] + c[2];
    } else if (/^[0-9A-Fa-f]{6}$/.test(c)) {
      c = '#' + c;
    }
    if (!/^#[0-9A-Fa-f]{6}$/.test(c)) return null;
    return c;
  }

  // applique la couleur de l'école sur toute la page (remplace le violet Corpo par défaut)
  function applySchoolThemeToDocument(ecole) {
    const sc = ecoleColor(ecole);
    const root = global.document && global.document.documentElement;
    const body = global.document && global.document.body;
    if (!root || !body) return sc;
    root.style.setProperty('--accent', sc);
    root.style.setProperty('--school-theme', sc);
    root.style.setProperty('--school-muted', hexToRgba(sc, 0.4));
    root.style.setProperty('--school-soft', hexToRgba(sc, 0.2));
    root.style.setProperty('--school-strong', hexToRgba(sc, 0.55));
    body.classList.add('tv-has-school');
    return sc;
  }

  // convertit le paramètre ?ecole vers la clé canonique (ECE, ESCE, HEIP, INSEEC, Sup de Pub)
  function normalizeEcoleKey(raw) {
    if (!raw) return null;
    const folded = fold(raw);
    if (!folded) return null;
    if (ECOLE_ALIASES[folded]) return ECOLE_ALIASES[folded];
    if (folded === 'inseec' || folded.startsWith('inseec ')) return 'INSEEC';
    for (const key of Object.keys(ECOLE_COLORS)) {
      if (fold(key) === folded) return key === 'INSEEC Bachelor' ? 'INSEEC' : key;
    }
    for (const prog of INSEEC_PROGRAMS) {
      if (fold(prog) === folded) return 'INSEEC';
    }
    return null;
  }

  function ecoleDisplayLabel(ecole) {
    if (ecole === 'INSEEC') return 'INSEEC';
    return ecole || '';
  }

  function ecoleColor(ecole) {
    if (!ecole) return TYPE_COLORS.Corpo;
    if (ecole === 'INSEEC') return ECOLE_COLORS.INSEEC;
    if (ECOLE_COLORS[ecole]) return ECOLE_COLORS[ecole];
    if (String(ecole).startsWith('INSEEC')) return ECOLE_COLORS.INSEEC;
    return ECOLE_COLORS[ecole] || ECOLE_COLORS.ECE;
  }

  function ecoleRowBackground(ecole, index) {
    const base = ecoleColor(ecole);
    const a = index % 2 === 0 ? 0.22 : 0.1;
    return hexToRgba(base, a);
  }

  function eventMatchesEcole(ecoles, ecoleCanon) {
    if (!ecoleCanon) return true;
    if (listHasTous(ecoles)) return true;
    if (ecoleCanon === 'INSEEC') {
      return ecoles.some((e) => String(e).toUpperCase().startsWith('INSEEC'));
    }
    const want = fold(ecoleCanon);
    return ecoles.some((e) => fold(e) === want);
  }

  function structureSpotlightColors(ev) {
    const fromStruct = normalizeHexColor(ev && ev.organisateur_color);
    if (fromStruct) {
      return {
        color: fromStruct,
        bg: hexToRgba(fromStruct, 0.55),
      };
    }
    const ecole = ev && ev.organisateur_ecole;
    if (ecole && fold(ecole) !== 'toutes') {
      const school = ecoleColor(ecole);
      return { color: school, bg: hexToRgba(school, 0.55) };
    }
    const type = (ev && ev.type) || 'Corpo';
    const color = TYPE_COLORS[type] || TYPE_COLORS.Corpo;
    return { color, bg: hexToRgba(color, 0.45) };
  }

  function normalizeCampusKey(raw) {
    if (!raw) return null;
    const folded = fold(raw);
    if (folded === 'citroen') return 'Citroen';
    if (folded === 'citadelle') return 'Citadelle';
    if (Object.prototype.hasOwnProperty.call(CAMPUS_ECOLES, String(raw).trim())) {
      return String(raw).trim();
    }
    return null;
  }

  function parseParams(search) {
    const p = new URLSearchParams(search || global.location.search);
    const campusKey = normalizeCampusKey(p.get('campus'));
    const ecoleRaw = (p.get('ecole') || '').trim() || null;
    const ecole = normalizeEcoleKey(ecoleRaw);
    return {
      ecole,
      ecoleRaw,
      ecoleInvalid: Boolean(ecoleRaw && !ecole),
      campus: campusKey,
      campusLabel: campusKey ? (CAMPUS_LABEL[campusKey] || campusKey) : null,
      ecoleLabel: ecole ? ecoleDisplayLabel(ecole) : null,
    };
  }

  function asList(val) {
    if (Array.isArray(val)) return val;
    if (val == null || val === '') return ['Tous'];
    return ['Tous'];
  }

  function listHasTous(list) {
    return list.includes('Tous') || list.includes('Toutes');
  }

  function matchTvEvent(ev, filters) {
    const ecoles = asList(ev.ecoles_invitees);
    const campus = asList(ev.campus_invites);

    if (filters.ecole) {
      return eventMatchesEcole(ecoles, filters.ecole);
    }

    if (filters.campus) {
      const inviteLabel = CAMPUS_INVITE_LABEL[filters.campus];
      const schools = CAMPUS_ECOLES[filters.campus] || [];

      const campusOk =
        listHasTous(campus) ||
        (inviteLabel && campus.includes(inviteLabel));

      if (!campusOk) return false;

      return listHasTous(ecoles) || schools.some((s) => ecoles.includes(s));
    }

    return true;
  }

  function appendApiParams(params, filters) {
    if (filters.ecole) params.set('ecole', filters.ecole);
    else if (filters.ecoleRaw) params.set('ecole', filters.ecoleRaw);
    if (filters.campus) params.set('campus', filters.campus);
  }

  global.TvFilters = {
    CAMPUS_ECOLES,
    CAMPUS_LABEL,
    CAMPUS_INVITE_LABEL,
    INSEEC_PROGRAMS,
    ECOLE_COLORS,
    TYPE_COLORS,
    parseParams,
    matchTvEvent,
    appendApiParams,
    normalizeCampusKey,
    normalizeEcoleKey,
    ecoleDisplayLabel,
    ecoleColor,
    hexToRgba,
    ecoleRowBackground,
    structureSpotlightColors,
    eventMatchesEcole,
    normalizeHexColor,
    applySchoolThemeToDocument,
  };
})(typeof window !== 'undefined' ? window : globalThis);
