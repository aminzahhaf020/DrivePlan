<?php

declare(strict_types=1);

session_start();

/*
 * Automatisch laden van classes uit de app-map.
 * Bijvoorbeeld App\Auth wordt geladen vanuit app/Auth.php.
 */
spl_autoload_register(function ($c) {
    $p = __DIR__ . '/../' . str_replace('\\', '/', $c) . '.php';

    if (file_exists($p)) {
        require $p;
    }
});

// Databaseverbinding laden.
$db = require __DIR__ . '/../config/database.php';

use App\Auth;
use App\Csrf;
use App\LessonService;


// Bepaalt welke pagina geopend moet worden.
$page = $_GET['page'] ?? 'home';

// Tijdelijke succes- en foutmeldingen ophalen.
$msg = $_SESSION['flash_ok'] ?? '';
$err = $_SESSION['flash_err'] ?? '';

unset($_SESSION['flash_ok'], $_SESSION['flash_err']);


/*
 * Stuurt de gebruiker door naar een andere pagina.
 * Een succes- of foutmelding kan worden meegenomen.
 */
function go(
    string $page = 'home',
    string $ok = '',
    string $error = ''
): never {

    if ($ok) {
        $_SESSION['flash_ok'] = $ok;
    }

    if ($error) {
        $_SESSION['flash_err'] = $error;
    }

    header('Location: /?page=' . urlencode($page));
    exit;
}


/*
 * Maakt tekst veilig voordat deze in HTML wordt getoond.
 * Dit helpt tegen XSS.
 */
function e($v): string
{
    return htmlspecialchars(
        (string)$v,
        ENT_QUOTES,
        'UTF-8'
    );
}


try {

    /*
     * UITLOGGEN
     * Verwijdert de sessie en stuurt de gebruiker terug naar de loginpagina.
     */
    if ($page === 'logout') {
        Auth::logout();

        header('Location: /?page=login');
        exit;
    }


    /*
     * POST-ACTIES
     * Alle formulieren worden hier verwerkt.
     * Eerst wordt het CSRF-token gecontroleerd.
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        Csrf::verify();


        /*
         * INLOGGEN
         * Zoekt de gebruiker op aan de hand van het e-mailadres.
         * Daarna wordt het wachtwoord gecontroleerd.
         */
        if ($page === 'login') {

            $email = trim($_POST['email'] ?? '');

            $s = $db->prepare(
                'SELECT u.*, r.name role
                 FROM users u
                 JOIN roles r ON r.id = u.role_id
                 WHERE email = ?'
            );

            $s->execute([$email]);
            $x = $s->fetch();

            if (
                $x &&
                password_verify(
                    $_POST['password'] ?? '',
                    $x['password_hash']
                )
            ) {
                Auth::login($x);

                go(
                    'home',
                    'Welkom bij DrivePlan.'
                );
            }

            throw new RuntimeException(
                'Inloggen is niet gelukt. Controleer je gegevens.'
            );
        }


        // Vanaf hier moet de gebruiker ingelogd zijn.
        Auth::requireLogin();

        $u = Auth::user();


        /*
         * ACCOUNT
         * Wijzigt de naam, het e-mailadres en de contactvoorkeur.
         */
        if ($page === 'account') {

            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $pref = $_POST['contact_preference'] ?? 'email';

            if (
                $name === '' ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                !in_array($pref, ['email', 'phone'], true)
            ) {
                throw new RuntimeException(
                    'Controleer je naam, e-mailadres en contactvoorkeur.'
                );
            }

            $s = $db->prepare(
                'UPDATE users
                 SET name=?, email=?, contact_preference=?
                 WHERE id=?'
            );

            $s->execute([
                $name,
                $email,
                $pref,
                $u['id']
            ]);

            // De sessie meteen bijwerken.
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;

            go(
                'account',
                'Accountgegevens zijn opgeslagen.'
            );
        }


        /*
         * RIJLES AANVRAGEN
         * Alleen een leerling mag een rijles aanvragen.
         */
        if ($page === 'request') {

            Auth::requireRole('student');

            (new LessonService($db))->request(
                $u['id'],
                (int)($_POST['instructor_id'] ?? 0),
                $_POST['start_at'] ?? '',
                $_POST['end_at'] ?? ''
            );

            go(
                'lessons',
                'Lesaanvraag is opgeslagen.'
            );
        }


        /*
         * BESCHIKBAARHEID TOEVOEGEN
         * Alleen een instructeur mag beschikbaarheid beheren.
         */
        if ($page === 'availability_add') {

            Auth::requireRole('instructor');

            $start = $_POST['start_at'] ?? '';
            $end = $_POST['end_at'] ?? '';
            $type = $_POST['type'] ?? '';

            if (
                !in_array(
                    $type,
                    ['available', 'blocked'],
                    true
                ) ||
                strtotime($start) === false ||
                strtotime($end) === false ||
                strtotime($start) >= strtotime($end)
            ) {
                throw new RuntimeException(
                    'Controleer de beschikbaarheid.'
                );
            }

            $s = $db->prepare(
                'INSERT INTO availability
                (instructor_id,start_at,end_at,type)
                VALUES(?,?,?,?)'
            );

            $s->execute([
                $u['id'],
                $start,
                $end,
                $type
            ]);

            go(
                'availability',
                'Beschikbaarheid is toegevoegd.'
            );
        }


        /*
         * BESCHIKBAARHEID VERWIJDEREN
         */
        if ($page === 'availability_delete') {

            Auth::requireRole('instructor');

            $s = $db->prepare(
                'DELETE FROM availability
                 WHERE id=?
                 AND instructor_id=?'
            );

            $s->execute([
                (int)$_POST['id'],
                $u['id']
            ]);

            go(
                'availability',
                'Beschikbaarheid is verwijderd.'
            );
        }


        /*
         * LES BEVESTIGEN
         */
        if ($page === 'confirm') {

            Auth::requireRole('instructor');

            (new LessonService($db))->confirm(
                (int)$_POST['lesson_id'],
                $u['id']
            );

            go(
                'lessons',
                'Les is bevestigd.'
            );
        }


        /*
         * LES VERPLAATSEN
         */
        if ($page === 'move') {

            Auth::requireRole('instructor');

            (new LessonService($db))->move(
                (int)$_POST['lesson_id'],
                $u['id'],
                $_POST['start_at'] ?? '',
                $_POST['end_at'] ?? ''
            );

            go(
                'lessons',
                'Les is verplaatst en bevestigd.'
            );
        }


        /*
         * LESGEGEVENS AANPASSEN
         * Hiermee worden locaties en de status opgeslagen.
         */
        if ($page === 'lesson_details') {

            Auth::requireRole('instructor');

            (new LessonService($db))->updateDetails(
                (int)$_POST['lesson_id'],
                $u['id'],
                trim($_POST['start_location'] ?? ''),
                trim($_POST['end_location'] ?? ''),
                $_POST['status'] ?? ''
            );

            go(
                'lessons',
                'Lesgegevens zijn opgeslagen.'
            );
        }
    }

} catch (Throwable $ex) {

    // Laat een duidelijke foutmelding aan de gebruiker zien.
    $err = $ex->getMessage();
}


// Controleert of iemand is ingelogd.
$u = Auth::user();

if (!$u && $page !== 'login') {
    $page = 'login';
}

?>

<!doctype html>

<html lang="nl">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>DrivePlan</title>

    <link
        rel="stylesheet"
        href="/style.css"
    >

</head>


<body>


<!-- =========================
     NAVIGATIE
========================= -->

<nav>

    <b>DrivePlan</b>

    <?php if ($u): ?>

        <a href="/">
            Dashboard
        </a>

        <a href="/?page=lessons">
            Rijlessen
        </a>

        <?php if ($u['role'] === 'student'): ?>

            <a href="/?page=request">
                Les aanvragen
            </a>

        <?php else: ?>

            <a href="/?page=availability">
                Beschikbaarheid
            </a>

        <?php endif; ?>

        <a href="/?page=account">
            Account
        </a>

        <a href="/?page=logout">
            Uitloggen
        </a>

    <?php endif; ?>

</nav>


<main class="wrap">


    <!-- Foutmelding -->
    <?php if ($err): ?>

        <div
            class="error"
            role="alert"
        >
            <?=e($err)?>
        </div>

    <?php endif; ?>


    <!-- Succesmelding -->
    <?php if ($msg): ?>

        <div class="ok">
            <?=e($msg)?>
        </div>

    <?php endif; ?>



    <!-- =========================
         LOGIN
    ========================== -->

    <?php if ($page === 'login'): ?>

        <section class="hero">

            <p class="hero-label">
                WELKOM BIJ DRIVEPLAN
            </p>

            <h1>
                Je rijlessen overzichtelijk op één plek
            </h1>

            <p class="muted">
                Log in als leerling of instructeur om je
                rijlessen en planning te beheren.
            </p>

        </section>


        <form
            class="panel"
            method="post"
        >

            <?=Csrf::field()?>

            <label for="email">
                E-mail
            </label>

            <input
                id="email"
                type="email"
                name="email"
                required
                autocomplete="email"
            >


            <label for="password">
                Wachtwoord
            </label>

            <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            >


            <button>
                Inloggen
            </button>

        </form>



    <!-- =========================
         ACCOUNT
    ========================== -->

    <?php elseif ($page === 'account'):

        Auth::requireLogin();

        $s = $db->prepare(
            'SELECT name,email,contact_preference
             FROM users
             WHERE id=?'
        );

        $s->execute([$u['id']]);

        $a = $s->fetch();

    ?>

        <p class="hero-label">
            MIJN PROFIEL
        </p>

        <h1>
            Mijn account
        </h1>

        <p class="muted">
            Bekijk en wijzig je persoonlijke gegevens.
        </p>


        <form
            class="panel"
            method="post"
        >

            <?=Csrf::field()?>


            <label>
                Naam
            </label>

            <input
                name="name"
                maxlength="100"
                value="<?=e($a['name'])?>"
                required
            >


            <label>
                E-mail
            </label>

            <input
                type="email"
                name="email"
                maxlength="150"
                value="<?=e($a['email'])?>"
                required
            >


            <label>
                Contactvoorkeur
            </label>

            <select name="contact_preference">

                <option
                    value="email"
                    <?=$a['contact_preference'] === 'email'
                        ? 'selected'
                        : ''
                    ?>
                >
                    E-mail
                </option>

                <option
                    value="phone"
                    <?=$a['contact_preference'] === 'phone'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Telefoon
                </option>

            </select>


            <button>
                Opslaan
            </button>

        </form>



    <!-- =========================
         RIJLES AANVRAGEN
    ========================== -->

    <?php elseif (
        $page === 'request' &&
        $u['role'] === 'student'
    ):

        $ins = $db->query(
            "SELECT u.id,u.name
             FROM users u
             JOIN roles r ON r.id=u.role_id
             WHERE r.name='instructor'
             ORDER BY u.name"
        )->fetchAll();

    ?>

        <p class="hero-label">
            NIEUWE RIJLES
        </p>

        <h1>
            Rijles aanvragen
        </h1>

        <p class="muted">
            Kies een instructeur en een tijd die binnen
            zijn beschikbaarheid valt.
        </p>


        <form
            class="panel"
            method="post"
        >

            <?=Csrf::field()?>


            <label>
                Instructeur
            </label>

            <select
                name="instructor_id"
                required
            >

                <?php foreach ($ins as $i): ?>

                    <option value="<?=e($i['id'])?>">
                        <?=e($i['name'])?>
                    </option>

                <?php endforeach; ?>

            </select>


            <div class="row">

                <div>

                    <label>
                        Start
                    </label>

                    <input
                        type="datetime-local"
                        name="start_at"
                        required
                    >

                </div>


                <div>

                    <label>
                        Einde
                    </label>

                    <input
                        type="datetime-local"
                        name="end_at"
                        required
                    >

                </div>

            </div>


            <button>
                Rijles aanvragen
            </button>

        </form>


        <div class="card">

            <p class="card-label">
                PLANNING
            </p>

            <h2>
                Beschikbare momenten
            </h2>


            <?php

            /*
             * Haalt alle algemene beschikbaarheid op
             * die nog niet voorbij is.
             */
            $av = $db->query(
                "SELECT a.*,u.name
                 FROM availability a
                 JOIN users u ON u.id=a.instructor_id
                 WHERE a.type='available'
                 AND a.end_at>NOW()
                 ORDER BY a.start_at
                 LIMIT 20"
            )->fetchAll();


            // Hier komen alleen de echt vrije tijdsblokken in.
            $free = [];


            foreach ($av as $x) {

                $slotStart = new DateTime(
                    $x['start_at']
                );

                $slotEnd = new DateTime(
                    $x['end_at']
                );


                /*
                 * Haalt lessen en blokkades binnen
                 * dit beschikbare tijdsblok op.
                 */
                $q = $db->prepare(
                    "SELECT start_at,end_at
                     FROM lessons
                     WHERE instructor_id=?
                     AND status IN ('confirmed','completed')
                     AND start_at < ?
                     AND end_at > ?

                     UNION ALL

                     SELECT start_at,end_at
                     FROM availability
                     WHERE instructor_id=?
                     AND type='blocked'
                     AND start_at < ?
                     AND end_at > ?

                     ORDER BY start_at"
                );


                $q->execute([
                    $x['instructor_id'],
                    $x['end_at'],
                    $x['start_at'],

                    $x['instructor_id'],
                    $x['end_at'],
                    $x['start_at']
                ]);


                $busy = $q->fetchAll();

                $cursor = clone $slotStart;


                foreach ($busy as $b) {

                    $busyStart = new DateTime(
                        $b['start_at']
                    );

                    $busyEnd = new DateTime(
                        $b['end_at']
                    );


                    /*
                     * Zorgt dat een bezet stuk niet
                     * buiten het beschikbare blok valt.
                     */
                    if ($busyStart < $slotStart) {
                        $busyStart = clone $slotStart;
                    }

                    if ($busyEnd > $slotEnd) {
                        $busyEnd = clone $slotEnd;
                    }


                    // Tijd vóór het bezette gedeelte is vrij.
                    if ($busyStart > $cursor) {

                        $free[] = [
                            'name' => $x['name'],
                            'start' => clone $cursor,
                            'end' => clone $busyStart
                        ];
                    }


                    // Daarna verder zoeken vanaf het einde.
                    if ($busyEnd > $cursor) {
                        $cursor = clone $busyEnd;
                    }
                }


                // Tijd na het laatste bezette stuk is ook vrij.
                if ($cursor < $slotEnd) {

                    $free[] = [
                        'name' => $x['name'],
                        'start' => clone $cursor,
                        'end' => clone $slotEnd
                    ];
                }
            }

            ?>


            <?php if (!$free): ?>

                <p class="muted">
                    Er zijn nog geen beschikbare momenten.
                </p>

            <?php else: ?>

                <?php foreach ($free as $x): ?>

                    <p>
                        <b><?=e($x['name'])?></b>
                        —
                        <?=e(
                            $x['start']->format(
                                'd-m-Y H:i'
                            )
                        )?>
                        tot
                        <?=e(
                            $x['end']->format(
                                'H:i'
                            )
                        )?>
                    </p>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>



    <!-- =========================
         BESCHIKBAARHEID
    ========================== -->

    <?php elseif (
        $page === 'availability' &&
        $u['role'] === 'instructor'
    ): ?>

        <p class="hero-label">
            MIJN PLANNING
        </p>

        <h1>
            Beschikbaarheid beheren
        </h1>

        <p class="muted">
            Geef aan wanneer je rijlessen kunt geven
            of wanneer je geblokkeerd bent.
        </p>


        <form
            class="panel"
            method="post"
            action="/?page=availability_add"
        >

            <?=Csrf::field()?>


            <div class="row">

                <div>

                    <label>
                        Start
                    </label>

                    <input
                        type="datetime-local"
                        name="start_at"
                        required
                    >

                </div>


                <div>

                    <label>
                        Einde
                    </label>

                    <input
                        type="datetime-local"
                        name="end_at"
                        required
                    >

                </div>


                <div>

                    <label>
                        Type
                    </label>

                    <select name="type">

                        <option value="available">
                            Beschikbaar
                        </option>

                        <option value="blocked">
                            Geblokkeerd
                        </option>

                    </select>

                </div>

            </div>


            <button>
                Toevoegen
            </button>

        </form>


        <?php

        // Haalt de beschikbaarheid van deze instructeur op.
        $s = $db->prepare(
            'SELECT *
             FROM availability
             WHERE instructor_id=?
             ORDER BY start_at'
        );

        $s->execute([$u['id']]);

        $rows = $s->fetchAll();

        ?>


        <table>

            <tr>
                <th>Start</th>
                <th>Einde</th>
                <th>Type</th>
                <th>Actie</th>
            </tr>


            <?php foreach ($rows as $x): ?>

                <tr>

                    <td>
                        <?=e($x['start_at'])?>
                    </td>

                    <td>
                        <?=e($x['end_at'])?>
                    </td>

                    <td>
                        <span class="badge">
                            <?=e($x['type'])?>
                        </span>
                    </td>

                    <td>

                        <form
                            method="post"
                            action="/?page=availability_delete"
                        >

                            <?=Csrf::field()?>

                            <input
                                type="hidden"
                                name="id"
                                value="<?=e($x['id'])?>"
                            >

                            <button class="danger">
                                Verwijderen
                            </button>

                        </form>

                    </td>

                </tr>

            <?php endforeach; ?>

        </table>



    <!-- =========================
         RIJLESSEN
    ========================== -->

    <?php elseif ($page === 'lessons'):

        Auth::requireLogin();

    ?>

        <p class="hero-label">
            DRIVEPLAN
        </p>

        <h1>
            Rijlessen
        </h1>

        <p class="muted">
            Bekijk en beheer je rijlessen.
        </p>


        <?php if ($u['role'] === 'student'):

            /*
             * Een leerling ziet alleen zijn eigen lessen.
             */
            $s = $db->prepare(
                'SELECT l.*,i.name instructor
                 FROM lessons l
                 JOIN users i
                 ON i.id=l.instructor_id
                 WHERE l.student_id=?
                 ORDER BY l.start_at DESC'
            );

            $s->execute([$u['id']]);

        ?>


            <table>

                <tr>
                    <th>Instructeur</th>
                    <th>Datum/tijd</th>
                    <th>Status</th>
                    <th>Locatie</th>
                </tr>


                <?php foreach ($s as $l): ?>

                    <tr>

                        <td>
                            <?=e($l['instructor'])?>
                        </td>

                        <td>
                            <?=e($l['start_at'])?>
                            -
                            <?=e($l['end_at'])?>
                        </td>

                        <td>
                            <span class="badge">
                                <?=e($l['status'])?>
                            </span>
                        </td>

                        <td>
                            <?=e(
                                $l['start_location']
                                ?: '-'
                            )?>

                            →

                            <?=e(
                                $l['end_location']
                                ?: '-'
                            )?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </table>


        <?php else:

            /*
             * Een instructeur ziet alleen de lessen
             * waarbij hij de instructeur is.
             */
            $s = $db->prepare(
                'SELECT l.*,st.name student
                 FROM lessons l
                 JOIN users st
                 ON st.id=l.student_id
                 WHERE l.instructor_id=?
                 ORDER BY l.start_at DESC'
            );

            $s->execute([$u['id']]);

            $ls = $s->fetchAll();

        ?>


            <?php if (!$ls): ?>

                <p class="muted">
                    Er zijn nog geen rijlessen.
                </p>

            <?php endif; ?>


            <?php foreach ($ls as $l): ?>

                <div class="card">

                    <p class="card-label">
                        RIJLES
                    </p>

                    <h2>

                        <?=e($l['student'])?>

                        <span class="badge">
                            <?=e($l['status'])?>
                        </span>

                    </h2>


                    <p>
                        <?=e($l['start_at'])?>
                        tot
                        <?=e($l['end_at'])?>
                    </p>


                    <?php if ($l['status'] === 'requested'): ?>

                        <!-- Les bevestigen -->
                        <form
                            method="post"
                            action="/?page=confirm"
                        >

                            <?=Csrf::field()?>

                            <input
                                type="hidden"
                                name="lesson_id"
                                value="<?=e($l['id'])?>"
                            >

                            <button>
                                Les bevestigen
                            </button>

                        </form>

                    <?php endif; ?>


                    <!-- Les verplaatsen -->
                    <form
                        method="post"
                        action="/?page=move"
                    >

                        <?=Csrf::field()?>

                        <input
                            type="hidden"
                            name="lesson_id"
                            value="<?=e($l['id'])?>"
                        >


                        <div class="row">

                            <div>

                                <label>
                                    Nieuwe start
                                </label>

                                <input
                                    type="datetime-local"
                                    name="start_at"
                                    required
                                >

                            </div>


                            <div>

                                <label>
                                    Nieuw einde
                                </label>

                                <input
                                    type="datetime-local"
                                    name="end_at"
                                    required
                                >

                            </div>

                        </div>


                        <button class="secondary">
                            Verplaatsen
                        </button>

                    </form>


                    <!-- Locaties en status aanpassen -->
                    <form
                        method="post"
                        action="/?page=lesson_details"
                    >

                        <?=Csrf::field()?>

                        <input
                            type="hidden"
                            name="lesson_id"
                            value="<?=e($l['id'])?>"
                        >


                        <label>
                            Startlocatie
                        </label>

                        <input
                            name="start_location"
                            maxlength="255"
                            value="<?=e($l['start_location'])?>"
                        >


                        <label>
                            Eindlocatie
                        </label>

                        <input
                            name="end_location"
                            maxlength="255"
                            value="<?=e($l['end_location'])?>"
                        >


                        <label>
                            Status
                        </label>

                        <select name="status">

                            <?php foreach (
                                [
                                    'confirmed',
                                    'completed',
                                    'cancelled'
                                ] as $st
                            ): ?>

                                <option
                                    value="<?=$st?>"
                                    <?=$l['status'] === $st
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    <?=$st?>
                                </option>

                            <?php endforeach; ?>

                        </select>


                        <button>
                            Lesgegevens opslaan
                        </button>

                    </form>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>



    <!-- =========================
         DASHBOARD
    ========================== -->

    <?php else:

        Auth::requireLogin();

    ?>


        <?php if ($u['role'] === 'student'): ?>


            <!-- Leerling dashboard -->
            <section class="hero">

                <p class="hero-label">
                    DRIVEPLAN VOOR LEERLINGEN
                </p>

                <h1>
                    Plan je rijlessen makkelijk en overzichtelijk
                </h1>

                <p class="muted">
                    Welkom <?=e($u['name'])?>.
                    Bekijk je lestegoed, houd je volgende
                    rijles bij en plan eenvoudig een nieuwe les.
                </p>


                <div class="hero-actions">

                    <a
                        class="btn"
                        href="/?page=request"
                    >
                        Rijles aanvragen
                    </a>

                    <a
                        class="btn secondary"
                        href="/?page=lessons"
                    >
                        Mijn rijlessen
                    </a>

                </div>

            </section>


            <?php

            /*
             * Lestegoed van de leerling ophalen.
             */
            $s = $db->prepare(
                'SELECT remaining_minutes,total_minutes
                 FROM lesson_credits
                 WHERE user_id=?'
            );

            $s->execute([$u['id']]);

            $c = $s->fetch() ?: [
                'remaining_minutes' => 0,
                'total_minutes' => 0
            ];


            /*
             * Eerstvolgende geplande rijles ophalen.
             */
            $n = $db->prepare(
                "SELECT l.*,i.name instructor
                 FROM lessons l
                 JOIN users i
                 ON i.id=l.instructor_id
                 WHERE l.student_id=?
                 AND l.status IN ('requested','confirmed')
                 AND l.end_at>=NOW()
                 ORDER BY l.start_at
                 LIMIT 1"
            );

            $n->execute([$u['id']]);

            $next = $n->fetch();

            ?>


            <div class="grid">


                <!-- Lestegoed -->
                <div class="card">

                    <p class="card-label">
                        LESTEGOED
                    </p>

                    <h2>
                        <?=e($c['remaining_minutes'])?>
                        minuten
                    </h2>

                    <p class="muted">
                        van
                        <?=e($c['total_minutes'])?>
                        minuten beschikbaar
                    </p>

                </div>


                <!-- Eerstvolgende les -->
                <div class="card">

                    <p class="card-label">
                        VOLGENDE RIJLES
                    </p>


                    <?php if ($next): ?>

                        <h2>
                            <?=e($next['instructor'])?>
                        </h2>

                        <p>
                            <?=e(
                                date(
                                    'd-m-Y H:i',
                                    strtotime(
                                        $next['start_at']
                                    )
                                )
                            )?>
                        </p>

                        <span class="badge">
                            <?=e($next['status'])?>
                        </span>


                    <?php else: ?>

                        <h2>
                            Nog geen les gepland
                        </h2>

                        <p class="muted">
                            Je hebt momenteel geen
                            aankomende rijles.
                        </p>

                    <?php endif; ?>

                </div>


                <!-- Nieuwe les -->
                <div class="card">

                    <p class="card-label">
                        NIEUWE RIJLES
                    </p>

                    <h2>
                        Klaar voor je volgende les?
                    </h2>

                    <p class="muted">
                        Bekijk de beschikbare momenten
                        en vraag een nieuwe rijles aan.
                    </p>

                    <a
                        class="btn"
                        href="/?page=request"
                    >
                        Rijles aanvragen
                    </a>

                </div>


            </div>


        <?php else: ?>


            <!-- Instructeur dashboard -->
            <section class="hero">

                <p class="hero-label">
                    DRIVEPLAN VOOR INSTRUCTEURS
                </p>

                <h1>
                    Beheer je rijlessen op één plek
                </h1>

                <p class="muted">
                    Welkom <?=e($u['name'])?>.
                    Bekijk nieuwe aanvragen, beheer je
                    beschikbaarheid en houd je planning overzichtelijk.
                </p>


                <div class="hero-actions">

                    <a
                        class="btn"
                        href="/?page=lessons"
                    >
                        Rijlessen bekijken
                    </a>

                    <a
                        class="btn secondary"
                        href="/?page=availability"
                    >
                        Beschikbaarheid beheren
                    </a>

                </div>

            </section>


            <?php

            /*
             * Telt hoeveel nieuwe aanvragen
             * deze instructeur heeft.
             */
            $s = $db->prepare(
                "SELECT COUNT(*)
                 FROM lessons
                 WHERE instructor_id=?
                 AND status='requested'"
            );

            $s->execute([$u['id']]);

            $count = $s->fetchColumn();

            ?>


            <div class="grid">


                <!-- Open aanvragen -->
                <div class="card">

                    <p class="card-label">
                        OPEN AANVRAGEN
                    </p>

                    <h2>
                        <?=e($count)?>
                        aanvraag/aanvragen
                    </h2>

                    <p class="muted">
                        Bekijk welke rijlessen
                        nog bevestigd moeten worden.
                    </p>

                    <a
                        class="btn"
                        href="/?page=lessons"
                    >
                        Aanvragen bekijken
                    </a>

                </div>


                <!-- Beschikbaarheid -->
                <div class="card">

                    <p class="card-label">
                        BESCHIKBAARHEID
                    </p>

                    <h2>
                        Beheer je planning
                    </h2>

                    <p class="muted">
                        Geef aan wanneer je beschikbaar
                        of geblokkeerd bent.
                    </p>

                    <a
                        class="btn"
                        href="/?page=availability"
                    >
                        Beschikbaarheid beheren
                    </a>

                </div>


                <!-- Rijlessen -->
                <div class="card">

                    <p class="card-label">
                        RIJLESSEN
                    </p>

                    <h2>
                        Lesoverzicht
                    </h2>

                    <p class="muted">
                        Bekijk, bevestig en verplaats
                        de rijlessen van je leerlingen.
                    </p>

                    <a
                        class="btn"
                        href="/?page=lessons"
                    >
                        Rijlessen bekijken
                    </a>

                </div>


            </div>


        <?php endif; ?>


    <?php endif; ?>


</main>

</body>

</html>