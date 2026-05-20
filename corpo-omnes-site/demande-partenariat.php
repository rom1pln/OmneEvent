<?php

require_once 'includes/db.php';

$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_contact  = trim($_POST['nom_contact']  ?? '');
    $email        = trim($_POST['email']        ?? '');
    $organisation = trim($_POST['organisation'] ?? '');
    $telephone    = trim($_POST['telephone']    ?? '');
    $type_offre   = trim($_POST['type_offre']   ?? '');
    $message      = trim($_POST['message']      ?? '');

    if ($nom_contact === '')  $errors[] = 'Le champ "Votre nom" est obligatoire.';
    if ($email === '')        $errors[] = 'L\'adresse e-mail est obligatoire.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'L\'adresse e-mail n\'est pas valide.';
    if ($organisation === '') $errors[] = 'Le champ "Entreprise" est obligatoire.';
    if ($message === '')      $errors[] = 'Veuillez décrire votre offre.';

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO demandes_partenariat
                (nom_contact, email, organisation, telephone, type_offre, message)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$nom_contact, $email, $organisation, $telephone, $type_offre, $message]);

        header('Location: demande-partenariat.php?sent=1');
        exit;
    }
}

$success = isset($_GET['sent']) && $_GET['sent'] === '1';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Devenir partenaire - Corpo Omnes Lyon</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-violet-950 text-white min-h-screen">

  <nav class="sticky top-0 z-50 bg-violet-950/90 backdrop-blur border-b border-violet-800/40">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">

      <a href="index.php" class="flex items-center gap-3">
        <img src="images/logo-corpo-omnes.png" alt="" class="h-8 w-auto" aria-hidden="true">
        <div class="leading-tight">
          <div class="font-bold text-sm">Corpo Omnes</div>
          <div class="text-xs text-violet-400">Lyon</div>
        </div>
      </a>

      <button id="tw-burger" aria-expanded="false" aria-controls="tw-menu"
        aria-label="Ouvrir le menu"
        class="md:hidden flex flex-col gap-1.5 p-2 rounded-lg hover:bg-violet-800/40 transition-colors">
        <span class="block w-6 h-0.5 bg-white transition-all"></span>
        <span class="block w-6 h-0.5 bg-white transition-all"></span>
        <span class="block w-6 h-0.5 bg-white transition-all"></span>
      </button>

      <ul class="hidden md:flex items-center gap-6 text-sm font-medium" role="list">
        <li><a href="index.php"        class="text-slate-300 hover:text-white transition-colors">Accueil</a></li>
        <li><a href="apropos.php"      class="text-slate-300 hover:text-white transition-colors">La Corpo</a></li>
        <li><a href="associations.php" class="text-slate-300 hover:text-white transition-colors">Associations</a></li>
        <li><a href="evenements.php"   class="text-slate-300 hover:text-white transition-colors">Événements</a></li>
        <li><a href="sport.php"        class="text-slate-300 hover:text-white transition-colors">Sport</a></li>
        <li><a href="partenaires.php"  class="text-slate-300 hover:text-white transition-colors">Partenaires</a></li>
        <li>
          <a href="demande-partenariat.php"
            class="bg-violet-600 hover:bg-violet-500 text-white font-semibold px-4 py-2 rounded-lg transition-colors text-xs uppercase tracking-wider">
            Devenir partenaire
          </a>
        </li>
      </ul>
    </div>

    <div id="tw-menu" class="hidden md:hidden border-t border-violet-800/40 bg-violet-950">
      <ul class="px-4 py-5 flex flex-col gap-4 text-sm font-medium" role="list">
        <li><a href="index.php"        class="block text-slate-300 hover:text-white transition-colors py-1">Accueil</a></li>
        <li><a href="apropos.php"      class="block text-slate-300 hover:text-white transition-colors py-1">La Corpo</a></li>
        <li><a href="associations.php" class="block text-slate-300 hover:text-white transition-colors py-1">Associations</a></li>
        <li><a href="evenements.php"   class="block text-slate-300 hover:text-white transition-colors py-1">Événements</a></li>
        <li><a href="sport.php"        class="block text-slate-300 hover:text-white transition-colors py-1">Sport</a></li>
        <li><a href="partenaires.php"  class="block text-slate-300 hover:text-white transition-colors py-1">Partenaires</a></li>
      </ul>
    </div>
  </nav>

  <main>

    <section class="py-16 px-4 text-center">
      <div class="max-w-2xl mx-auto">
        <nav aria-label="Fil d'Ariane" class="flex items-center justify-center gap-2 text-xs text-slate-400 mb-8">
          <a href="index.php" class="hover:text-white transition-colors">Accueil</a>
          <span aria-hidden="true">›</span>
          <a href="partenaires.php" class="hover:text-white transition-colors">Partenaires</a>
          <span aria-hidden="true">›</span>
          <span class="text-slate-300">Devenir partenaire</span>
        </nav>
        <span class="inline-block text-xs font-bold uppercase tracking-widest text-violet-400 mb-4">Partenariat</span>
        <h1 class="text-4xl md:text-6xl font-black mb-6 leading-tight">
          Touchez <span class="text-violet-400">6 000</span><br>étudiants lyonnais
        </h1>
        <p class="text-slate-300 text-base md:text-lg leading-relaxed">
          La Corpo Omnes Lyon fédère les 5 écoles du groupe Omnes sur les campus Citroën et Citadelle.
          Devenez partenaire et intégrez un réseau étudiant actif et engagé.
        </p>
      </div>
    </section>

    <section class="px-4 pb-12">
      <div class="max-w-6xl mx-auto grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-violet-900/30 border border-violet-800/40 rounded-2xl p-8 text-center"><div class="text-5xl font-black text-violet-400 mb-2">6 000</div><div class="text-sm text-slate-300 uppercase tracking-wider">Étudiants touchés</div></div>
        <div class="bg-violet-900/30 border border-violet-800/40 rounded-2xl p-8 text-center"><div class="text-5xl font-black text-violet-400 mb-2">31</div><div class="text-sm text-slate-300 uppercase tracking-wider">Associations</div></div>
        <div class="bg-violet-900/30 border border-violet-800/40 rounded-2xl p-8 text-center"><div class="text-5xl font-black text-violet-400 mb-2">2</div><div class="text-sm text-slate-300 uppercase tracking-wider">Campus lyonnais</div></div>
      </div>
    </section>

    <section class="px-4 py-12 bg-violet-900/10">
      <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">

                <div class="bg-violet-900/25 border border-violet-800/40 rounded-2xl p-6 md:p-8">
          <h2 class="text-2xl font-bold mb-2">Déposer une demande</h2>
          <p class="text-sm text-slate-400 mb-6">Réponse garantie sous 5 jours ouvrés.</p>

          <?php if ($success): ?>
            <div class="bg-green-900/40 border border-green-500/50 text-green-300 rounded-xl px-5 py-4 mb-6 text-sm">
              Votre demande a bien été envoyée ! Nous vous répondrons sous 5 jours ouvrés.
            </div>
          <?php endif; ?>

          <?php if (!empty($errors)): ?>
            <div class="bg-red-900/40 border border-red-500/50 text-red-300 rounded-xl px-5 py-4 mb-6 text-sm">
              <strong>Erreur :</strong>
              <ul class="mt-1 list-disc list-inside">
                <?php foreach ($errors as $err): ?>
                  <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="post" action="demande-partenariat.php" class="flex flex-col gap-5">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="organisation" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Entreprise <span class="text-violet-400">*</span></label>
                <input id="organisation" name="organisation" type="text" placeholder="Nom de votre entreprise" required
                  value="<?= htmlspecialchars($_POST['organisation'] ?? '') ?>"
                  class="w-full bg-violet-950/60 border border-violet-700/50 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-violet-400 transition-colors">
              </div>
              <div>
                <label for="nom_contact" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Votre nom <span class="text-violet-400">*</span></label>
                <input id="nom_contact" name="nom_contact" type="text" placeholder="Prénom Nom" required
                  value="<?= htmlspecialchars($_POST['nom_contact'] ?? '') ?>"
                  class="w-full bg-violet-950/60 border border-violet-700/50 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-violet-400 transition-colors">
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Email <span class="text-violet-400">*</span></label>
                <input id="email" name="email" type="email" placeholder="vous@entreprise.fr" required
                  value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                  class="w-full bg-violet-950/60 border border-violet-700/50 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-violet-400 transition-colors">
              </div>
              <div>
                <label for="telephone" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Téléphone</label>
                <input id="telephone" name="telephone" type="tel" placeholder="06 00 00 00 00"
                  value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>"
                  class="w-full bg-violet-950/60 border border-violet-700/50 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-violet-400 transition-colors">
              </div>
            </div>

            <div>
              <label for="type_offre" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Type de partenariat <span class="text-violet-400">*</span></label>
              <select id="type_offre" name="type_offre"
                class="w-full bg-violet-950/60 border border-violet-700/50 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-violet-400 transition-colors">
                <option value="" class="bg-violet-950">Sélectionner un type…</option>
                <option value="remise"      class="bg-violet-950" <?= ($_POST['type_offre'] ?? '') === 'remise'      ? 'selected' : '' ?>>Réduction / offre étudiante</option>
                <option value="evenement"   class="bg-violet-950" <?= ($_POST['type_offre'] ?? '') === 'evenement'   ? 'selected' : '' ?>>Parrainage d'événement</option>
                <option value="conference"  class="bg-violet-950" <?= ($_POST['type_offre'] ?? '') === 'conference'  ? 'selected' : '' ?>>Conférence / intervention métier</option>
                <option value="rse"         class="bg-violet-950" <?= ($_POST['type_offre'] ?? '') === 'rse'         ? 'selected' : '' ?>>Partenariat RSE / solidaire</option>
                <option value="recrutement" class="bg-violet-950" <?= ($_POST['type_offre'] ?? '') === 'recrutement' ? 'selected' : '' ?>>Recrutement / stage / alternance</option>
                <option value="autre"       class="bg-violet-950" <?= ($_POST['type_offre'] ?? '') === 'autre'       ? 'selected' : '' ?>>Autre</option>
              </select>
            </div>

            <div>
              <label for="message" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Offre proposée <span class="text-violet-400">*</span></label>
              <textarea id="message" name="message" rows="4" required
                placeholder="Décrivez votre offre, les avantages pour les étudiants, le code promo éventuel, le budget…"
                class="w-full bg-violet-950/60 border border-violet-700/50 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-violet-400 transition-colors resize-none"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>

            <label class="flex items-start gap-3 cursor-pointer">
              <input type="checkbox" required name="consent" class="accent-violet-400 w-4 h-4 mt-0.5 flex-shrink-0">
              <span class="text-xs text-slate-400">J'accepte que mes informations soient utilisées par la Corpo Omnes Lyon pour traiter ma demande de partenariat.</span>
            </label>

            <button type="submit"
              class="w-full bg-violet-600 hover:bg-violet-500 active:bg-violet-700 text-white font-bold py-4 rounded-xl transition-colors text-sm uppercase tracking-widest">
              Envoyer ma demande →
            </button>

          </form>
        </div>

                <div class="flex flex-col gap-6">
          <h2 class="text-2xl font-bold">Pourquoi nous rejoindre ?</h2>
          <p class="text-slate-300 text-sm leading-relaxed">
            Devenir partenaire de la Corpo, c'est bénéficier d'une visibilité directe auprès de 6 000 étudiants en commerce, ingénierie, sciences politiques, communication et marketing.
          </p>
          <div class="flex flex-col gap-3">
            <div class="flex items-start gap-4 bg-violet-900/20 border border-violet-800/30 rounded-xl p-5"><span class="text-2xl flex-shrink-0" aria-hidden="true">🎯</span><div><h3 class="font-bold text-sm mb-1">Audience qualifiée</h3><p class="text-xs text-slate-400 leading-relaxed">Étudiants de bac+1 à bac+5 dans des filières à fort pouvoir d'achat futur.</p></div></div>
            <div class="flex items-start gap-4 bg-violet-900/20 border border-violet-800/30 rounded-xl p-5"><div><h3 class="font-bold text-sm mb-1">Visibilité multi-canal</h3><p class="text-xs text-slate-400 leading-relaxed">Site web, réseaux sociaux, affichage campus et relai auprès des 31 associations.</p></div></div>
            <div class="flex items-start gap-4 bg-violet-900/20 border border-violet-800/30 rounded-xl p-5"><div><h3 class="font-bold text-sm mb-1">Interlocuteur dédié</h3><p class="text-xs text-slate-400 leading-relaxed">Elyam Lalaouui, Responsable Partenariat, assure le suivi de votre dossier.</p></div></div>
            <div class="flex items-start gap-4 bg-violet-900/20 border border-violet-800/30 rounded-xl p-5"><span class="text-2xl flex-shrink-0" aria-hidden="true">🌱</span><div><h3 class="font-bold text-sm mb-1">Impact RSE valorisé</h3><p class="text-xs text-slate-400 leading-relaxed">Partenariats solidaires bienvenus, valorisés auprès de notre communauté engagée.</p></div></div>
          </div>
          <div class="bg-violet-600/15 border border-violet-500/30 rounded-xl p-5">
            <p class="text-xs font-bold uppercase tracking-wider text-violet-400 mb-3">Contact direct</p>
            <p class="text-sm font-bold">Elyam Lalaouui</p>
            <p class="text-xs text-slate-400 mb-3">Responsable Partenariat</p>
            <a href="mailto:corpoomnes@gmail.com" class="text-xs text-violet-400 hover:text-violet-300 transition-colors break-all">corpoomnes@gmail.com</a>
          </div>
        </div>

      </div>
    </section>

  </main>

  <footer class="border-t border-violet-800/40 py-8 px-4">
    <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-slate-400">
      <span>© <?= date('Y') ?> Corpo Omnes Lyon</span>
      <div class="flex flex-wrap justify-center gap-2 text-xs font-bold">
        <span class="px-2 py-1 rounded bg-teal-900/50   text-teal-400   border border-teal-800/50">ECE</span>
        <span class="px-2 py-1 rounded bg-blue-900/50   text-blue-400   border border-blue-800/50">ESCE</span>
        <span class="px-2 py-1 rounded bg-red-900/50    text-red-400    border border-red-800/50">HEIP</span>
        <span class="px-2 py-1 rounded bg-indigo-900/50 text-indigo-400 border border-indigo-800/50">INSEEC</span>
        <span class="px-2 py-1 rounded bg-orange-900/50 text-orange-400 border border-orange-800/50">Sup de Pub</span>
      </div>
    </div>
  </footer>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    $(function () {
      var $burger = $('
      $burger.on('click', function () {
        var isOpen = !$menu.hasClass('hidden');
        $menu.toggleClass('hidden', isOpen);
        $burger.attr('aria-expanded', String(!isOpen));
      });
      $menu.on('click', 'a', function () {
        $menu.addClass('hidden');
        $burger.attr('aria-expanded', 'false');
      });
    });
  </script>

</body>
</html>
