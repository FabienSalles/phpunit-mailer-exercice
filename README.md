# PHPUnit Training — Refactoring Email avec Snapshot Tests

## Contexte

Vous travaillez sur une application e-commerce. La classe `OrderNotificationManager` gère l'envoi de tous les emails transactionnels : confirmation de commande, notification d'expédition et relance.

Cette classe est directement couplée à `Symfony\Component\Mailer\MailerInterface`. Elle a 7 dépendances constructeur, construit du HTML via Twig, et envoie les emails en dur. Aucun test n'existe.

**Pire**, on ne peut même pas faire tourner l'application pour visualiser les emails « à la main » : il n'y a ni front-end ni commande CLI pour déclencher un envoi, et `UserService` / `InvoiceService` interrogent des APIs externes fictives. Aucun moyen de vérifier qu'une modification ne casse pas un email en production.

**La parade**, ce sont les snapshot tests : on capture une fois pour toutes le HTML produit par `OrderNotificationManager`, on le fige sur disque, et tout changement futur fait remonter un diff explicite. C'est notre seul moyen de voir ce que le manager produit.

Votre mission tient en trois temps :

1. **Sécuriser le code legacy avec des snapshot tests** pour figer le comportement avant tout changement.
2. **Extraire une abstraction** (`SendEmailInterface`) pour découpler l'envoi d'email du transport SMTP.
3. **Brancher un second transport** (`ApiEmailSender`) qui passe par une API HTTP, en gardant les snapshots intacts pour prouver que rien n'a bougé côté contenu.

## Pré-requis

- Docker & Docker Compose

## Installation

```bash
make install
```

> Si `composer install` échoue avec « lock file is not up to date » (notamment sur `step-3-solution`, qui ajoute `symfony/http-client`), régénère le lock avec `make shell` puis `composer update`. Le `composer.lock` n'est pas committé.

## Commandes disponibles

| Commande         | Description                        |
|------------------|------------------------------------|
| `make install`   | Installer les dépendances          |
| `make tests`     | Lancer les tests PHPUnit           |
| `make cs-check`  | Vérifier le coding style           |
| `make cs-fix`    | Corriger le coding style           |
| `make shell`     | Ouvrir un shell dans le container  |

---

## Étape 1 — Sécuriser le legacy avec des snapshot tests

**Objectif :** Ajouter des tests sur `OrderNotificationManager` **sans modifier le code de production**. Les snapshots capturent le HTML généré et deviennent la référence à comparer après chaque changement.

### Ce qui est fourni

- `tests/Service/OrderNotificationManagerTest.php` : squelette avec data providers en `\Generator` et tests à compléter (`markTestIncomplete`)
- `phpunit.xml.dist` + `config/services_test.yaml` : config de test ; `MailerInterface` est exposé comme alias public pour pouvoir le remplacer dans les tests

### Les services à stubber

Deux services appellent de vraies APIs HTTP au runtime :

| Service          | Méthode                           | Endpoint                                                |
|------------------|-----------------------------------|---------------------------------------------------------|
| `UserService`    | `getCustomerPreferences($id)`     | `GET api.customer.internal/customers/{id}/preferences`  |
| `InvoiceService` | `generateInvoicePdf($id)`         | `GET api.billing.internal/invoices/{id}.pdf`            |

`OrderNotificationManager` appelle `getCustomerPreferences` au début de chaque méthode et skippe l'envoi si `notifications: false`. Le stub doit donc renvoyer `['locale' => 'fr', 'notifications' => true]` pour que le test atteigne l'envoi de l'email et puisse l'inspecter.

### À faire

1. **Créer `StubbedMailer`** : une classe qui implémente `MailerInterface` à la main pour capturer en mémoire l'`Email` qui aurait été envoyé. `send()` enregistre l'argument reçu ; `getSentEmail(): Email` l'expose au test. C'est un double manuel (on aurait pu passer par `createStub` ou Prophecy) qui fait à la fois stub et spy.
2. **Implémenter les 3 tests** (`testSendOrderConfirmation`, `testSendShippingNotification`, `testSendOrderReminder`) :
   - Booter le kernel
   - Injecter le `StubbedMailer` dans le container
   - Stubber `UserService` (toujours), `InvoiceService` (pour shipping)
   - Récupérer `OrderNotificationManager` depuis le container (pas de `new`)
   - Appeler la méthode, récupérer l'email capturé
   - Vérifier `from` / `to` / `subject` en **une seule** `assertEquals` contre une structure attendue
   - Vérifier le HTML avec `$this->assertMatchesHtmlSnapshot($emailSent->getHtmlBody())`
3. **Commiter les snapshots générés** dans `tests/Service/__snapshots__/` — ils font partie du filet de sécurité.

### Conventions

- Data providers en `\Generator` (`yield 'nom' => [...]`)
- DAMP > DRY : entrées, **préparation des stubs** et assertions restent inline (elles décrivent le scénario) ; seul le boot du kernel peut être déporté en `setUp()` si on préfère
- Une seule `assertEquals` contre un array structuré plutôt que plusieurs `assertSame` orthogonaux
- Factory methods statiques pour les fixtures
- Prophecy (`->willReturn(...)`) pour les services à stubber, plutôt que des mocks avec `expects()->with()` — aucune assertion avant l'act

### Vérification

```bash
make tests
# OK (7 tests, 14 assertions) ✅
```

---

## Étape 2 — Extraire une abstraction (`SendEmailInterface`)

**Objectif :** Découpler `OrderNotificationManager` de `MailerInterface` en introduisant une interface domaine. **Les snapshots de l'étape 1 doivent toujours passer sans modification.**

### Pourquoi

Demain, on voudra pouvoir envoyer les emails via un service de notification HTTP (cf. étape 3) sans toucher au code métier. On introduit donc un contrat :

```php
interface SendEmailInterface
{
    public function __invoke(Email $email): void;
}
```

### À faire

1. Créer `src/Spi/Mailer/SendEmailInterface.php`
2. Créer `src/Spi/Mailer/SmtpEmailSender.php` qui wrappe `MailerInterface` (le comportement actuel)
3. Créer `tests/StubbedEmailSender.php` — même principe que le `StubbedMailer` de l'étape 1, transposé sur la nouvelle interface.
4. Modifier `OrderNotificationManager` : `MailerInterface` → `SendEmailInterface`
5. Mettre à jour `services.yaml` et `services_test.yaml` (alias `SendEmailInterface` sur `SmtpEmailSender`)
6. Mettre à jour les tests : `StubbedEmailSender` à la place de `StubbedMailer`
7. Supprimer `tests/StubbedMailer.php`

### Vérification

```bash
make tests
# OK (7 tests, 14 assertions) ✅
# Snapshots inchangés → le refactor n'a rien cassé
```

---

## Étape 3 — Brancher un second transport (`ApiEmailSender`)

**Objectif :** Ajouter une seconde implémentation de `SendEmailInterface` qui POST l'email vers une API HTTP, et basculer la prod dessus. Les snapshots passent toujours.

> `symfony/http-client` est déjà présent depuis l'étape 1.

### À faire

1. Créer `src/Spi/Mailer/ApiEmailSender.php` : sérialise l'`Email` en JSON (`from`, `to`, `subject`, `body`, `attachments` en base64) et POST sur `{API_URL}/emails` avec un header `Authorization: Bearer {API_KEY}`.
2. Créer `tests/Spi/Mailer/ApiEmailSenderTest.php` (unitaire, `TestCase`). Utiliser `MockHttpClient` + `MockResponse` — la `MockResponse` enregistre la requête, accessible via `getRequestMethod()` / `getRequestUrl()` / `getRequestOptions()` après l'act.
3. Déclarer `ApiEmailSender` dans `services.yaml` et basculer l'alias `SendEmailInterface` dessus.
4. Refléter le changement d'alias dans `services_test.yaml`.
5. Ajouter `NOTIFICATION_API_URL` et `NOTIFICATION_API_KEY` dans `.env`.

### Vérification

```bash
make tests
# OK (9 tests, 18 assertions) ✅
```

Les snapshots de l'étape 1 passent toujours — alors que le transport prod a complètement changé.

---

## Architecture finale

```
AVANT (step-1)                          APRÈS (step-3)
┌─────────────────────┐                 ┌─────────────────────┐
│ OrderNotification   │                 │ OrderNotification   │
│ Manager             │                 │ Manager             │
│                     │                 │                     │
│  MailerInterface ◄──┤── couplage      │  SendEmailInterface │
│  (Symfony)          │   direct        │  (notre interface)  │
└─────────────────────┘                 └────────┬────────────┘
                                                 │
                                    ┌────────────┴────────────┐
                                    │            │            │
                              SmtpEmailSender   ApiEmailSender
                              (legacy)          (prod active)

                                                StubbedEmailSender
                                                (tests)
```

## Branches & corrections

L'historique est linéaire : chaque branche solution prolonge la précédente d'un seul commit. Les PR montrent les diffs étape par étape.

| Branche             | Contenu                                                                  | PR                                                                            |
|---------------------|--------------------------------------------------------------------------|-------------------------------------------------------------------------------|
| `step-1-start`      | Code legacy + squelette de test à compléter                              | —                                                                             |
| `step-1-solution`   | `StubbedMailer` + 3 tests + 7 snapshots                                  | [#1](https://github.com/FabienSalles/phpunit-mailer-exercice/pull/1)          |
| `step-2-solution`   | `SendEmailInterface` + `SmtpEmailSender` + `StubbedEmailSender`          | [#2](https://github.com/FabienSalles/phpunit-mailer-exercice/pull/2)          |
| `step-3-solution`   | `ApiEmailSender` + test unitaire + bascule de l'alias vers l'API         | [#3](https://github.com/FabienSalles/phpunit-mailer-exercice/pull/3)          |

### Visualiser les corrections

Localement :

```bash
git diff step-1-start..step-1-solution     # solution de l'étape 1
git diff step-1-solution..step-2-solution  # solution de l'étape 2
git diff step-2-solution..step-3-solution  # solution de l'étape 3
```

Ou directement via les PR ci-dessus.

---

## Conclusion

Trois petites étapes — sécuriser, abstraire, basculer — qui sont la procédure standard sur n'importe quel module legacy non testé.

### Sécuriser avant de refactorer

Le principe vient de *Working Effectively with Legacy Code* (Michael Feathers) : sans tests décrivant le comportement actuel, **tout refactor est aveugle**. Les snapshots de l'étape 1 sont des *characterization tests* — ils figent le réel, pas un idéal. C'est ce qui rend les étapes 2 et 3 sereines.

### Strangler Fig / Branch by Abstraction

Le combo « interface + bascule d'alias DI » est une instance des patterns de Martin Fowler ([Strangler Fig](https://martinfowler.com/bliki/StranglerFigApplication.html), [Branch by Abstraction](https://martinfowler.com/bliki/BranchByAbstraction.html)). On introduit un point de couture, on garde l'ancien transport derrière, puis on branche le nouveau — la prod tourne pendant tout le refactor.

### Dependency Inversion + ports & adapters

`OrderNotificationManager` ne dépend que de `SendEmailInterface`, un contrat **qui appartient à l'application**. Les implémentations (SMTP, API HTTP, stub) vivent en périphérie. C'est la lecture pratique des architectures hexagonales / ports-and-adapters : le métier au centre, les transports interchangeables autour.

### Test doubles : stub, spy, mock

Taxonomie de Gerard Meszaros (*xUnit Test Patterns*) :

- **Stub** : réponses pré-définies (`UserService` / `InvoiceService` via Prophecy → `->willReturn(...)`).
- **Spy** : enregistre les appels pour qu'on les vérifie *après* l'act (`StubbedMailer` / `StubbedEmailSender` codés à la main, `MockResponse::getRequest*()` côté Symfony HttpClient).
- **Mock** : configure des *expectations* avant l'act (`->expects()->with()`).

Le mock force à assertir avant d'agir, ce qui casse l'AAA. On l'évite : pour les stubs on passe par **Prophecy** (API qui se limite à `willReturn` sans déclaration d'attente), et pour les spies on écrit le double à la main pour pouvoir l'interroger après l'act.

### AAA + DAMP — la nuance

- **AAA** : un seul appel métier par test, toutes les assertions le suivent.
- **DAMP > DRY** : seul le **boot du kernel** est purement technique et pourrait vivre dans un `setUp()`. La **préparation des stubs** (`UserService` qui renvoie `notifications: true`, `InvoiceService` qui renvoie un PDF factice) **ne peut pas** y aller : elle décrit le scénario testé, elle change d'un cas à l'autre, et un test où on doit aller voir ailleurs ce que renvoient les services perd son intérêt. C'est ça qui reste inline dans chaque test.

### `assertEquals` consolidé plutôt qu'une chaîne de `assertSame`

```php
self::assertSame('noreply@...', $email->getFrom()[0]->getAddress());                  // ❌ fail ici
self::assertSame($order->getCustomerEmail(), $email->getTo()[0]->getAddress());       // jamais évalué
self::assertSame($expectedSubject, $email->getSubject());                             // jamais évalué
```

PHPUnit ne signale que la première qui casse. Si `to` et `subject` sont **aussi** mauvais, on l'apprendra au prochain run, puis au suivant.

```php
self::assertEquals(
    [
        'from' => 'noreply@...',
        'to' => $order->getCustomerEmail(),
        'subject' => $expectedSubject,
    ],
    [
        'from' => $email->getFrom()[0]->getAddress(),
        'to' => $email->getTo()[0]->getAddress(),
        'subject' => $email->getSubject(),
    ],
);
```

Le diff PHPUnit montre **les trois champs en désaccord** d'un coup quand il y en a trois, ou la seule sur trois quand il n'y en a qu'une. Debug accéléré, intention plus claire.

### Test d'intégration via le container — pourquoi pas juste `new` ?

Un `new OrderNotificationManager(...)` avec ses arguments stubés est parfaitement valide et plus léger. Mais `self::getContainer()->get(...)` exécute aussi la déclaration de `services.yaml` — le test couvre donc **en plus** le câblage DI (un mauvais alias, un argument oublié, et le test crie).

Le revers : ce type de test peut vite devenir lourd. Il faut faire attention à ce qu'on embarque — ici on stube tout ce qui touche au réseau pour rester rapide.

### En résumé

| Pratique                         | Pourquoi                                                                  |
|----------------------------------|---------------------------------------------------------------------------|
| Snapshot tests                   | Figent un comportement complexe (HTML) qu'on ne testerait pas ligne à ligne |
| Characterization avant refactor  | Aucun refactor n'est sûr sans tests qui décrivent l'existant              |
| Abstraction + ports/adapters     | Découple le métier du transport, ouvre à plusieurs implémentations        |
| Stub + spy manuels               | Lisibles, AAA naturel, pas d'API de doublure à apprendre                  |
| `assertEquals` consolidé         | Un diff complet plutôt qu'une erreur à la fois                            |
| Test via le container            | Vérifie le câblage DI en plus du métier — au prix d'un test plus lourd    |
