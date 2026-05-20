// Données statiques des assos - TODO: remplacer par des appels API quand le backend sera prêt

const ASSOCIATIONS = [

  // inter-écoles
  {
    id: 1, slug: "corpo-omnes",
    nom: "Corpo OMNES",
    ecole: "Toutes",
    type: "Corpo",
    campus: "Tous",
    description: "L'association fédératrice inter-écoles qui coordonne les BDE, accompagne les associations et mutualise les ressources pour une vie étudiante riche sur les deux campus lyonnais.",
    membres: 15,
    contact: "corpoomnes@gmail.com",
    instagram: "corpo_omnes",
    events: ["Week-end d'intégration inter-écoles", "Gala Omnes", "Forum des assos"],
    ouverteATous: true,
    color: "#5D0282"
  },
  {
    id: 2, slug: "omnes-sport",
    nom: "OMNES Sport",
    ecole: "Toutes",
    type: "BDS",
    campus: "Tous",
    description: "Le Bureau des Sports inter-écoles coordonne toutes les activités sportives des deux campus. Tournois, entraînements hebdomadaires et compétitions inter-établissements sur 8 disciplines.",
    membres: 30,
    contact: "sport.omnes.lyon@gmail.com",
    instagram: "omnes_sport_lyon",
    events: ["Tournoi de football inter-écoles", "Tournoi basket 3×3", "Challenge sportif Omnes"],
    ouverteATous: true,
    color: "#8B2FC9"
  },

  // BDE
  {
    id: 3, slug: "bde-shot",
    nom: "BDE Shot",
    ecole: "Sup de Pub",
    type: "BDE",
    campus: "Citadelle",
    description: "Le BDE de Sup de Pub anime la communauté des futurs créatifs et communicants. Ateliers créatifs, soirées à thème, portfolio day et projets de communication.",
    membres: 16,
    contact: "bde.shot.lyon@gmail.com",
    instagram: "bde_shot_lyon",
    events: ["Nuit de la créativité", "Portfolio Day", "Soirée Art & Com"],
    ouverteATous: false,
    color: "#FF5B05"
  },
  {
    id: 4, slug: "bde-ginfinity",
    nom: "BDE Ginfinity",
    ecole: "ECE",
    type: "BDE",
    campus: "Citadelle",
    description: "Le Bureau des Étudiants de l'ECE, Ginfinity, anime la vie étudiante des futurs ingénieurs et ingénieures d'Omnes Lyon. Soirées, intégration, voyages et projets associatifs.",
    membres: 18,
    contact: "bde.ginfinity.lyon@gmail.com",
    instagram: "bde_ginfinity",
    events: ["Soirée d'intégration ECE", "Week-end ski ECE", "Tournoi inter-promo"],
    ouverteATous: false,
    color: "#007179"
  },
  {
    id: 5, slug: "bde-hyperion",
    nom: "BDE Hyperion",
    ecole: "HEIP",
    type: "BDE",
    campus: "Citadelle",
    description: "Le BDE de l'HEIP, Hyperion, représente les étudiants en sciences politiques et relations internationales. Débats, conférences, voyages diplomatiques et événements culturels.",
    membres: 14,
    contact: "bde.hyperion.lyon@gmail.com",
    instagram: "bde_hyperion_heip",
    events: ["Conférence politique", "Voyage à Bruxelles", "Soirée des Nations"],
    ouverteATous: false,
    color: "#E52521"
  },
  {
    id: 6, slug: "bde-newolf",
    nom: "BDE Newolf",
    ecole: "ESCE",
    type: "BDE",
    campus: "Citroën",
    description: "Le BDE de l'ESCE, Newolf, porte la vie étudiante des futurs managers internationaux. Événements, networking, soirées et actions solidaires pour toute la promo.",
    membres: 22,
    contact: "bde.newolf.lyon@gmail.com",
    instagram: "bde_newolf_esce",
    events: ["Gala ESCE", "Speed Business Meeting", "Soirée d'intégration ESCE"],
    ouverteATous: false,
    color: "#002D74"
  },
  {
    id: 7, slug: "bde-insolute",
    nom: "BDE In'Solute",
    ecole: "INSEEC",
    type: "BDE",
    campus: "Citroën",
    description: "Le BDE Grande École de l'INSEEC, In'Solute, représente les étudiants du programme GE. Événements festifs, projets associatifs et gestion de la vie de campus au quotidien.",
    membres: 20,
    contact: "bde.insolute.lyon@gmail.com",
    instagram: "bde_insolute",
    events: ["Soirée Casino", "Gala GE", "Tournoi inter-BDE INSEEC"],
    ouverteATous: false,
    color: "#003DA5"
  },
  {
    id: 8, slug: "bde-instables",
    nom: "BDE In'Stables",
    ecole: "INSEEC",
    type: "BDE",
    campus: "Citroën",
    description: "Le BDE BBA de l'INSEEC, In'Stables, anime la vie étudiante du Bachelor in Business Administration. Soirées, voyages et événements pour la communauté BBA.",
    membres: 18,
    contact: "bde.instables.lyon@gmail.com",
    instagram: "bde_instables",
    events: ["Soirée BBA", "Weekend cohésion", "Forum entreprises BBA"],
    ouverteATous: false,
    color: "#003DA5"
  },
  {
    id: 9, slug: "bde-the-hangover",
    nom: "BDE The Hangover",
    ecole: "INSEEC",
    type: "BDE",
    campus: "Citroën",
    description: "Le BDE Bachelor de l'INSEEC, The Hangover, représente les étudiants du cursus Bachelor. Soirées mémorables, intégration des nouveaux et animation du campus Citroën.",
    membres: 16,
    contact: "bde.hangover.lyon@gmail.com",
    instagram: "bde_thehangover",
    events: ["Soirée d'intégration Bachelor", "Afterwork étudiants", "Tournoi sportif Bachelor"],
    ouverteATous: false,
    color: "#003DA5"
  },
  {
    id: 10, slug: "bde-paradise",
    nom: "BDE Paradise",
    ecole: "INSEEC",
    type: "BDE",
    campus: "Citroën",
    description: "Le BDE MSc de l'INSEEC, Paradise, anime la communauté des étudiants en Master of Science. Networking professionnel, conférences et événements pour les spécialistes de demain.",
    membres: 14,
    contact: "bde.paradise.lyon@gmail.com",
    instagram: "bde_paradise_msc",
    events: ["Conférence MSc", "Gala MSc", "Business Game"],
    ouverteATous: false,
    color: "#003DA5"
  },

  // HEIP
  {
    id: 11, slug: "echofed",
    nom: "EchoFed",
    ecole: "HEIP",
    type: "Fédération",
    campus: "Citadelle",
    description: "La fédération des associations de l'HEIP. EchoFed coordonne et représente l'ensemble des clubs et associations de l'école au sein du campus Citadelle.",
    membres: 10,
    contact: "echofed.heip@gmail.com",
    instagram: "echofed_heip",
    events: ["Forum des assos HEIP", "Réunion de coordination", "Gala HEIP"],
    ouverteATous: false,
    color: "#E52521"
  },
  {
    id: 12, slug: "agora-nostra",
    nom: "Agora Nostra",
    ecole: "HEIP",
    type: "Association",
    campus: "Citadelle",
    description: "Association de diplomatie et politique de l'HEIP. Simulations de négociations, conférences d'élus, modèles ONU et débats sur les grands enjeux géopolitiques contemporains.",
    membres: 25,
    contact: "agora.nostra.heip@gmail.com",
    instagram: "agora_nostra_heip",
    events: ["Modèle ONU Lyon", "Conférence géopolitique", "Simulation Conseil de l'UE"],
    ouverteATous: false,
    color: "#E52521"
  },
  {
    id: 13, slug: "terra-vitaia",
    nom: "Terra Vitaia",
    ecole: "HEIP",
    type: "Association",
    campus: "Citadelle",
    description: "Association humanitaire de l'HEIP. Projets de terrain, collectes solidaires et sensibilisation aux causes humanitaires internationales. Agir localement, penser globalement.",
    membres: 30,
    contact: "terra.vitaia.heip@gmail.com",
    instagram: "terra_vitaia",
    events: ["Collecte humanitaire", "Conférence ONG", "Marathon solidaire"],
    ouverteATous: false,
    color: "#E52521"
  },
  {
    id: 14, slug: "aequalis",
    nom: "Aequalis",
    ecole: "HEIP",
    type: "Association",
    campus: "Citadelle",
    description: "Association pour le droit des femmes de l'HEIP. Sensibilisation aux inégalités de genre, conférences féministes, débats et actions de plaidoyer pour l'égalité.",
    membres: 20,
    contact: "aequalis.heip@gmail.com",
    instagram: "aequalis_heip",
    events: ["Journée des droits des femmes", "Table ronde égalité", "Ciné-débat féministe"],
    ouverteATous: false,
    color: "#E52521"
  },
  {
    id: 15, slug: "invino-veritas",
    nom: "InVino Veritas",
    ecole: "HEIP",
    type: "Association",
    campus: "Citadelle",
    description: "Club œnologie de l'HEIP. Dégustations commentées, visites de domaines viticoles en Bourgogne et Rhône, soirées vins du monde et formation à la culture du vin.",
    membres: 18,
    contact: "invino.heip@gmail.com",
    instagram: "invino_veritas_heip",
    events: ["Dégustation grands crus", "Visite domaine Côtes du Rhône", "Soirée vins du monde"],
    ouverteATous: false,
    color: "#E52521"
  },
  {
    id: 16, slug: "oratores",
    nom: "Oratores",
    ecole: "HEIP",
    type: "Association",
    campus: "Citadelle",
    description: "Club d'éloquence de l'HEIP. Art oratoire, débats contradictoires, joutes verbales et préparation aux concours de plaidoirie. Apprendre à convaincre avec style.",
    membres: 22,
    contact: "oratores.heip@gmail.com",
    instagram: "oratores_heip",
    events: ["Tournoi d'éloquence HEIP", "Concours de plaidoirie", "Atelier prise de parole"],
    ouverteATous: false,
    color: "#E52521"
  },
  {
    id: 17, slug: "definseec",
    nom: "Def'Inseec",
    ecole: "HEIP",
    type: "Association",
    campus: "Citadelle",
    description: "Association défense et sécurité nationale de l'HEIP. Conférences avec des acteurs de la défense, visites d'institutions militaires et réflexions sur les enjeux stratégiques.",
    membres: 15,
    contact: "definseec.heip@gmail.com",
    instagram: "definseec",
    events: ["Conférence défense nationale", "Visite École militaire", "Débat OTAN"],
    ouverteATous: false,
    color: "#E52521"
  },

  // ESCE
  {
    id: 18, slug: "cine-club-esce",
    nom: "Ciné Club",
    ecole: "ESCE",
    type: "Association",
    campus: "Citroën",
    description: "Le club cinéma de l'ESCE. Projections en avant-première, soirées ciné-débat, analyse filmique et sorties au cinéma d'art et essai à Lyon.",
    membres: 20,
    contact: "cineclub.esce.lyon@gmail.com",
    instagram: "cineclub_esce",
    events: ["Soirée ciné-débat", "Projection avant-première", "Festival court-métrage"],
    ouverteATous: false,
    color: "#002D74"
  },
  {
    id: 19, slug: "assocuisto",
    nom: "AssoCuisto",
    ecole: "ESCE",
    type: "Association",
    campus: "Citroën",
    description: "L'association cuisine de l'ESCE. Cours de cuisine animés par des étudiants ou des chefs, dîners thématiques, soirées gastronomiques et partage autour de la table.",
    membres: 25,
    contact: "assocuisto.esce@gmail.com",
    instagram: "assocuisto_esce",
    events: ["Atelier cuisine du monde", "Dîner gastronomique", "Battle culinaire"],
    ouverteATous: false,
    color: "#002D74"
  },
  {
    id: 20, slug: "promesce",
    nom: "PromESCE",
    ecole: "ESCE",
    type: "Association",
    campus: "Citroën",
    description: "L'association de promotion de l'ESCE. Cohésion de promo, création du trombinoscope, organisation des souvenirs de fin d'études et animation de la vie de promo.",
    membres: 12,
    contact: "promesce.lyon@gmail.com",
    instagram: "promesce_lyon",
    events: ["Soirée promo", "Trombinoscope officiel", "Week-end cohésion promo"],
    ouverteATous: false,
    color: "#002D74"
  },
  {
    id: 21, slug: "bds-esce",
    nom: "BDS ESCE",
    ecole: "ESCE",
    type: "BDS",
    campus: "Citroën",
    description: "Le Bureau des Sports de l'ESCE organise les activités sportives pour les étudiants du campus Citroën. Tournois internes, inscriptions sportives et équipes ESCE.",
    membres: 16,
    contact: "bds.esce.lyon@gmail.com",
    instagram: "bds_esce",
    events: ["Tournoi interne ESCE", "Journée sport ESCE", "Équipe ESCE challenge"],
    ouverteATous: false,
    color: "#002D74"
  },

  // ECE
  {
    id: 22, slug: "bds-ece",
    nom: "BDS ECE",
    ecole: "ECE",
    type: "BDS",
    campus: "Citadelle",
    description: "Le Bureau des Sports de l'ECE coordonne les activités sportives pour les étudiants ingénieurs. Tournois internes, équipes ECE et représentation sportive de l'école.",
    membres: 20,
    contact: "bds.ece.lyon@gmail.com",
    instagram: "bds_ece_lyon",
    events: ["Tournoi sportif ECE", "Challenge inter-promo", "Journée sport ECE"],
    ouverteATous: false,
    color: "#007179"
  },
  {
    id: 23, slug: "jeece",
    nom: "JEECE",
    ecole: "ECE",
    type: "Junior",
    campus: "Citadelle",
    description: "La Junior-Entreprise de l'ECE Lyon réalise des missions de conseil, développement logiciel et ingénierie pour des clients réels. Formation professionnelle et réseau alumni.",
    membres: 22,
    contact: "jeece.lyon@gmail.com",
    instagram: "jeece_lyon",
    events: ["Forum entreprises ECE", "Conférence tech", "Journée portes ouvertes JEECE"],
    ouverteATous: false,
    color: "#007179"
  },
  {
    id: 24, slug: "arece",
    nom: "ARECE",
    ecole: "ECE",
    type: "Association",
    campus: "Citadelle",
    description: "Association de robotique et de courses autonomes de l'ECE Lyon. Conception et pilotage de véhicules autonomes, participation à des compétitions nationales de robotique.",
    membres: 18,
    contact: "arece.lyon@gmail.com",
    instagram: "arece_lyon",
    events: ["Compétition robotique nationale", "Atelier électronique", "Demo ARECE"],
    ouverteATous: false,
    color: "#007179"
  },
  {
    id: 25, slug: "ece-automobile-club",
    nom: "ECE Automobile Club",
    ecole: "ECE",
    type: "Association",
    campus: "Citadelle",
    description: "Le club automobile de l'ECE rassemble les passionnés d'automobile et de mécanique. Sorties, rallyes étudiants, conférences constructeurs et visites d'usines.",
    membres: 15,
    contact: "ece.autoclub@gmail.com",
    instagram: "ece_auto_club",
    events: ["Sortie circuit Paul Ricard", "Visite usine Renault", "Rallye étudiant"],
    ouverteATous: false,
    color: "#007179"
  },
  {
    id: 26, slug: "hello-tech-girl",
    nom: "Hello Tech Girl",
    ecole: "ECE",
    type: "Association",
    campus: "Citadelle",
    description: "Association de promotion des femmes dans les sciences et les technologies à l'ECE. Mentorat, conférences inspirantes et sensibilisation aux carrières STEM pour toutes.",
    membres: 28,
    contact: "hellotechgirl.ece@gmail.com",
    instagram: "hellotechgirl_ece",
    events: ["Conférence femmes en tech", "Atelier coding girls", "Journée STEM"],
    ouverteATous: false,
    color: "#007179"
  },
  {
    id: 27, slug: "loophole",
    nom: "LoopHole",
    ecole: "ECE",
    type: "Association",
    campus: "Citadelle",
    description: "Le club musique de l'ECE. Concerts live, ateliers musicaux, studio d'enregistrement et jams sessions. Tous les styles, tous les instruments, toutes les passions.",
    membres: 24,
    contact: "loophole.ece@gmail.com",
    instagram: "loophole_ece",
    events: ["Concert de fin d'année ECE", "Jam session mensuelle", "Atelier production musicale"],
    ouverteATous: false,
    color: "#007179"
  },
  {
    id: 28, slug: "lyontech",
    nom: "LyonTech",
    ecole: "ECE",
    type: "Association",
    campus: "Citadelle",
    description: "Association technique et innovation de l'ECE Lyon. Hackathons, projets IoT, intelligence artificielle, développement logiciel et rencontres avec l'écosystème tech lyonnais.",
    membres: 32,
    contact: "lyontech.ece@gmail.com",
    instagram: "lyontech_ece",
    events: ["Hackathon LyonTech", "Conférence IA & Data", "Atelier Arduino/Raspberry Pi"],
    ouverteATous: false,
    color: "#007179"
  },
  {
    id: 29, slug: "ece-finance",
    nom: "ECE Finance",
    ecole: "ECE",
    type: "Association",
    campus: "Citadelle",
    description: "Club finance et marchés financiers de l'ECE. Simulations boursières, analyse de marchés, conférences avec des professionnels et initiation à l'investissement.",
    membres: 20,
    contact: "ece.finance.lyon@gmail.com",
    instagram: "ece_finance",
    events: ["Simulation boursière", "Conférence marchés financiers", "Atelier analyse technique"],
    ouverteATous: false,
    color: "#007179"
  },
  {
    id: 30, slug: "tutorat-ece",
    nom: "Tutorat ECE",
    ecole: "ECE",
    type: "Association",
    campus: "Citadelle",
    description: "Association de tutorat de l'ECE. Étudiants avancés accompagnent les plus juniors en maths, physique, informatique et autres matières. Solidarité académique entre ingénieurs.",
    membres: 35,
    contact: "tutorat.ece.lyon@gmail.com",
    instagram: "tutorat_ece",
    events: ["Sessions de révisions partiels", "Permanence tutorat", "Soirée révisions 24h"],
    ouverteATous: false,
    color: "#007179"
  },
  {
    id: 31, slug: "sdi-ece",
    nom: "SDI",
    ecole: "ECE",
    type: "Association",
    campus: "Citadelle",
    description: "Le Séminaire d'Intégration de l'ECE organise l'accueil des nouveaux étudiants. Week-end d'intégration, parrainage des premières années et création du lien entre les promos.",
    membres: 16,
    contact: "sdi.ece.lyon@gmail.com",
    instagram: "sdi_ece_lyon",
    events: ["Week-end d'intégration ECE", "Soirée parrainage", "Journée cohésion première année"],
    ouverteATous: false,
    color: "#007179"
  }
];

const EVENEMENTS = [
  {
    id: 1,
    titre: "Soirée d'intégration",
    date: "2026-09-30",
    heure: "20h00",
    lieu: "À définir",
    campus: "Tous campus",
    organisateur: "Corpo Omnes Lyon",
    type: "Corpo",
    priority: 1,
    description: "La grande soirée d'intégration inter-écoles pour accueillir les nouveaux étudiants des cinq écoles Omnes Lyon. Une nuit pour se rencontrer, créer des liens et démarrer l'année ensemble.",
    inscriptions: true,
    places: 300,
    icon: "🎉"
  }
];

const SPORTS = [
  {
    id: 1, slug: "basket", categorie: "club",
    nom: "Basketball", icon: "🏀", couleur: "#FF9500",
    description: "Deux séances par semaine au gymnase Citroën. On joue en championnat universitaire lyonnais et on organise un tournoi 3×3 à l'automne. Tous niveaux bienvenus.",
    referents: [
      { initiales: "MB", nom: "Marc Bonnet", role: "Capitaine", email: "basket.omnes.lyon@gmail.com" }
    ],
    entrainements: [
      { jour: "Lundi",    heure: "19h00 – 21h00", lieu: "Gymnase Campus Citroën" },
      { jour: "Vendredi", heure: "17h00 – 19h00", lieu: "Gymnase Campus Citroën" }
    ],
    evenements: [
      { titre: "Tournoi 3×3 inter-écoles", date: "2026-10-17", lieu: "Gymnase Campus Citroën" }
    ],
    resultats: [
      { adversaire: "INSA Lyon",       score: "72 – 58", date: "2026-03-15", victoire: true  },
      { adversaire: "Sciences Po Lyon", score: "61 – 65", date: "2026-02-28", victoire: false }
    ],
    places: 15, inscrits: 10, campus: "Citroën"
  },
  {
    id: 2, slug: "foot", categorie: "club",
    nom: "Football", icon: "⚽", couleur: "#27AE60",
    description: "Mardi et jeudi soir à Villeurbanne. L'équipe dispute le championnat universitaire avec les autres grandes écoles lyonnaises. Quelques places encore dispo pour la saison.",
    referents: [
      { initiales: "TL", nom: "Thomas Leroy", role: "Capitaine", email: "foot.omnes.lyon@gmail.com" }
    ],
    entrainements: [
      { jour: "Mardi", heure: "18h00 – 20h00", lieu: "Stade Municipal Villeurbanne" },
      { jour: "Jeudi", heure: "18h00 – 20h00", lieu: "Stade Municipal Villeurbanne" }
    ],
    evenements: [
      { titre: "Tournoi inter-écoles Omnes", date: "2026-10-03", lieu: "Stade Municipal Villeurbanne" }
    ],
    resultats: [
      { adversaire: "EM Lyon",       score: "3 – 1", date: "2026-03-22", victoire: true },
      { adversaire: "Centrale Lyon", score: "2 – 2", date: "2026-03-08", victoire: null }
    ],
    places: 20, inscrits: 14, campus: "Tous"
  },
  {
    id: 3, slug: "rugby", categorie: "club",
    nom: "Rugby", icon: "🏉", couleur: "#E52521",
    description: "Le club le plus engagé du campus. Mercredi soir et samedi matin au stade de Villeurbanne. On joue en compétitions régionales - pas besoin d'avoir déjà joué.",
    referents: [
      { initiales: "AL", nom: "Alexandre Lopes", role: "Capitaine", email: "rugby.omnes.lyon@gmail.com" }
    ],
    entrainements: [
      { jour: "Mercredi", heure: "17h30 – 19h30", lieu: "Stade Municipal Villeurbanne" },
      { jour: "Samedi",   heure: "10h00 – 12h00", lieu: "Stade Municipal Villeurbanne" }
    ],
    evenements: [
      { titre: "Challenge Universitaire Rhône-Alpes", date: "2026-11-14", lieu: "Stade de la Plaine - Lyon" }
    ],
    resultats: [
      { adversaire: "Grenoble INP", score: "18 – 22", date: "2026-03-19", victoire: false },
      { adversaire: "IEP Lyon",     score: "31 – 12", date: "2026-03-05", victoire: true  }
    ],
    places: 22, inscrits: 16, campus: "Tous"
  },
  {
    id: 4, slug: "cheerleading", categorie: "club",
    nom: "Cheerleading", icon: "📣", couleur: "#8B2FC9",
    description: "Zéro prérequis, zéro prise de tête. On répète mardi soir et jeudi midi à Citadelle. L'équipe prépare un showcase pour décembre - les nouvelles têtes sont les bienvenues.",
    referents: [
      { initiales: "CL", nom: "Camille Laurent", role: "Capitaine", email: "cheer.omnes.lyon@gmail.com" }
    ],
    entrainements: [
      { jour: "Mardi", heure: "18h30 – 20h00", lieu: "Gymnase Campus Citadelle" },
      { jour: "Jeudi", heure: "12h30 – 14h00", lieu: "Gymnase Campus Citadelle" }
    ],
    evenements: [
      { titre: "Showcase inter-écoles", date: "2026-12-05", lieu: "Campus Citadelle - Grand Hall" }
    ],
    resultats: [
      { adversaire: "Showcase rentrée", score: "Médaille d'argent", date: "2026-09-20", victoire: true }
    ],
    places: 20, inscrits: 17, campus: "Citadelle"
  }
];

const PARTENAIRES = [
  {
    id: 1,
    nom: "Urban Gym Lyon",
    type: "Sport",
    logo: "images/partner-placeholder.png",
    offre: "−20% sur l'abonnement mensuel",
    code: "OMNES20",
    campus: "Tous",
    lien: "#",
    description: "Accès à 3 salles de sport à Lyon 7, Lyon 3 et Villeurbanne. Cours collectifs inclus avec le code promo."
  },
  {
    id: 2,
    nom: "Café Confluences",
    type: "Restauration",
    logo: "images/partner-placeholder.png",
    offre: "Café offert pour tout achat",
    code: "CORPO2026",
    campus: "Citadelle",
    lien: "#",
    description: "Bar-restaurant à deux pas du campus Citadelle. Menu étudiant à 9€ le midi, du lundi au vendredi."
  },
  {
    id: 3,
    nom: "La Bibliothèque du Cinéma",
    type: "Culture",
    logo: "images/partner-placeholder.png",
    offre: "Tarif étudiant −30%",
    code: "OMNESCULTURE",
    campus: "Tous",
    lien: "#",
    description: "Séances de cinéma d'art et d'essai à Lyon. Accès à la médiathèque cinématographique sur présentation de la carte étudiante."
  },
  {
    id: 4,
    nom: "Maison de la Danse",
    type: "Culture",
    logo: "images/partner-placeholder.png",
    offre: "Places à 7€ (prix normal 22€)",
    code: "BDEOMNES",
    campus: "Tous",
    lien: "#",
    description: "Réductions sur les spectacles de danse contemporaine à Lyon. Quota de places réservées aux étudiants Omnes chaque saison."
  },
  {
    id: 5,
    nom: "Co-Working Station Lyon",
    type: "Travail",
    logo: "images/partner-placeholder.png",
    offre: "1 journée gratuite / mois",
    code: "CORPORWORK",
    campus: "Tous",
    lien: "#",
    description: "Espace de co-working au cœur de Lyon Confluence. Idéal pour les projets associatifs, les juniors entreprises et les startups étudiantes."
  },
  {
    id: 6,
    nom: "Association solidaire du Rhône",
    type: "RSE",
    logo: "images/partner-placeholder.png",
    offre: "Opportunités de bénévolat",
    code: null,
    campus: "Tous",
    lien: "#",
    description: "Partenariat RSE : les étudiants peuvent rejoindre les actions de terrain (maraudes, ateliers) et valider des heures de bénévolat."
  }
];

// Accesseurs (à remplacer par fetch() quand le backend PHP sera prêt)
function getAssociations(filters = {}) {
  let data = [...ASSOCIATIONS];
  if (filters.type)   data = data.filter(a => a.type === filters.type);
  if (filters.ecole)  data = data.filter(a => a.ecole === filters.ecole || a.ecole === "Toutes");
  if (filters.campus) data = data.filter(a => a.campus === filters.campus || a.campus === "Tous");
  if (filters.search) {
    const q = filters.search.toLowerCase();
    data = data.filter(a => a.nom.toLowerCase().includes(q) || a.description.toLowerCase().includes(q));
  }
  return data;
}

function getAssociationBySlug(slug) {
  return ASSOCIATIONS.find(a => a.slug === slug) || null;
}

function getEvenements(filters = {}) {
  let data = [...EVENEMENTS];
  if (filters.type) data = data.filter(e => e.type === filters.type);
  if (filters.campus && filters.campus !== "Tous") data = data.filter(e => e.campus === filters.campus || e.campus === "Tous campus");
  data.sort((a, b) => {
    if (a.priority !== b.priority) return a.priority - b.priority;
    return new Date(a.date) - new Date(b.date);
  });
  return data;
}

function getSports() {
  return [...SPORTS];
}

function getPartenaires(filters = {}) {
  let data = [...PARTENAIRES];
  if (filters.type) data = data.filter(p => p.type === filters.type);
  return data;
}
