<?php

namespace App\Support;

/**
 * The first version of the privacy page, as editor content. Only used to fill the page the first
 * time; after that Ine edits it in the admin (Privacy). Keep it true to what the site really does:
 * a check of the code is in the comments of each section.
 */
class PrivacyText
{
    public static function doc(): array
    {
        return ['type' => 'doc', 'content' => [
            self::p(self::b('Even eerlijk over je gegevens 🔒')),
            self::p(self::t('Fijn dat je hier rondneust! Ik ben Ine, en ik schrijf deze blog zelf, op mijn eigen server. Ik vind privacy belangrijk: ik wil weten wat er met mijn gegevens gebeurt, dus jij mag dat van mij ook weten. Hieronder lees je wat deze site wel en vooral níet bijhoudt.')),

            self::h('In het kort'),
            self::ul([
                'Je kunt alles lezen zonder dat ik iets van je bewaar. Geen tracking, geen advertenties, geen cookies om je te volgen.',
                'Alleen als je zelf iets doet (je aanmelden voor mijn mailtjes, een reactie achterlaten of een hartje geven) bewaar ik wat daarvoor nodig is.',
                'Ik verkoop of deel niets. Ik zou niet eens weten aan wie 😄',
            ]),

            // SubscriberController + Subscriber: name, email, hashed IP; double opt-in; prune after 30 days
            self::h('Mailtje bij een nieuw verhaal'),
            self::p(self::t('Wil je een mailtje als er een nieuwe blog online staat? Dan bewaar ik je naam en e-mailadres. Je krijgt eerst een mail om je aanmelding te bevestigen, zodat niemand anders jou kan aanmelden. Bevestig je niet, dan verdwijnen je gegevens na 30 dagen vanzelf.')),
            self::p(self::t('Onderaan elke mail staat een link om je af te melden. Klik je die aan, dan haal ik je gegevens meteen weg. ')),

            // CommentController + Response: name, email (never shown), text, hashed IP, user agent
            self::h('Reacties'),
            self::p(self::t('Laat je een reactie achter, dan bewaar ik je naam, je e-mailadres en wat je schrijft. Je naam en je reactie staan op de site, je e-mailadres nooit: dat gebruik ik alleen als je zelf aanvinkt dat je een mailtje wilt als ik terugschrijf. Een eerste reactie lees ik eerst even voordat hij online komt.')),
            self::p(self::t('Om spam tegen te houden bewaar ik ook welk soort browser je gebruikte en een versleutelde "vingerafdruk" van je internetadres (IP-adres). Daaruit valt niet terug te halen wie je bent. Spamreacties gooi ik na 30 dagen automatisch weg; echte reacties blijven staan zolang het verhaal online staat, tenzij je vraagt of ik ze weghaal.')),

            // Laravel session + XSRF-TOKEN cookie on every page (120 minutes); Visitor: visitor_id, 2 years, only on clicking the heart
            self::h('Cookies'),
            self::p(self::t('Als je de site bezoekt, krijgt je browser twee kleine cookies: een sessiecookie en een beveiligingscookie. Die zorgen ervoor dat formulieren (een reactie, je aanmelding) veilig verstuurd worden en niet door iemand anders namens jou. Er staat niets over jou in, en ze verlopen na twee uur.')),
            self::p(self::t('Klik je op het hartje, dan komt er één cookie bij met een willekeurige code, zodat je een verhaal maar één keer een hartje kunt geven. Die code zegt niets over wie je bent, en ik bewaar hem alleen versleuteld. Hij blijft maximaal twee jaar bewaard; je kunt hem altijd zelf verwijderen in je browser.')),
            self::p(self::t('Dit zijn allemaal cookies die nodig zijn om de site te laten werken. Daarom zie je bij mij geen cookiemelding: er valt niets weg te klikken 🍪')),

            // ViewCounter: daily hash of IP + browser + blog, no analytics service
            self::h('Hoe vaak een verhaal gelezen wordt'),
            self::p(self::t('Ik ben best benieuwd welke verhalen jullie leuk vinden, dus ik tel hoe vaak een blog bekeken wordt. Dat doe ik zelf, zonder Google Analytics of iets dergelijks: de site onthoudt alleen een versleutelde code per bezoek per dag, zodat één bezoeker niet tien keer meetelt. Wie je bent weet ik niet, en wil ik ook niet weten.')),

            self::h('Wie mij helpen'),
            self::p(self::t('Een paar bedrijven helpen me om deze site te laten draaien. Ze krijgen alleen wat ze voor hun taak nodig hebben:')),
            self::ul([
                'Hetzner (Duitsland): hier staat de server met de blog. Zoals elke webserver houdt hij kort bij welke pagina\'s er worden opgevraagd, met het IP-adres erbij. Die logboeken worden na twee weken automatisch weggegooid.',
                'Brevo (Frankrijk): verstuurt de mailtjes (je bevestiging, nieuwe verhalen, antwoorden op je reactie). Brevo krijgt daarvoor je naam en e-mailadres. Ik heb het meten van wie mails opent en waarop geklikt wordt zo ver mogelijk uitgezet: Brevo telt alleen nog anoniem.',
                'Cloudflare: de "ik ben geen robot"-check onder het reactieformulier en de aanmelding voor de mailtjes. Cloudflare kijkt daarvoor naar je browser en je IP-adres, alleen op het moment dat je zo\'n formulier gebruikt.',
                'Esri: levert de kaartjes op de reiskaart en bij mijn reizen. Als je een kaart bekijkt, haalt je browser de stukjes kaart bij Esri op, en ziet Esri dus je IP-adres.',
            ]),
            self::p(self::t('De lettertypes, icoontjes en alle andere onderdelen van de site staan gewoon op mijn eigen server. De deelknoppen onder een verhaal zijn gewone links: er gaat pas iets naar Facebook, WhatsApp en co als je er zelf op klikt.')),

            self::h('Jouw gegevens, jouw keuze'),
            self::p(self::t('Wil je weten wat ik van je bewaar, wil je iets laten aanpassen of wil je dat ik alles weghaal? Stuur me een mailtje via '), self::link('inetjuhhh@gmail.com', 'mailto:inetjuhhh@gmail.com'), self::t(' en ik regel het. Ben je het ergens niet mee eens, dan mag je ook altijd een klacht indienen bij de '), self::link('Autoriteit Persoonsgegevens', 'https://www.autoriteitpersoonsgegevens.nl'), self::t(', maar laten we het eerst samen proberen 😊')),

            self::p(self::i('Deze tekst pas ik aan als er op de site iets verandert. Laatst bijgewerkt: oktober 2026.')),
        ]];
    }

    private static function h(string $text): array
    {
        return ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [self::t($text)]];
    }

    private static function p(array ...$inline): array
    {
        return ['type' => 'paragraph', 'content' => $inline];
    }

    /** @param list<string> $items */
    private static function ul(array $items): array
    {
        return ['type' => 'bulletList', 'content' => array_map(
            fn (string $item) => ['type' => 'listItem', 'content' => [self::p(...self::labelled($item))]],
            $items,
        )];
    }

    /**
     * "Brevo (Frankrijk): verstuurt …" with the part before the colon in bold.
     */
    private static function labelled(string $item): array
    {
        if (preg_match('/^([^:]{2,40}): (.+)$/u', $item, $m)) {
            return [self::b($m[1] . ':'), self::t(' ' . $m[2])];
        }

        return [self::t($item)];
    }

    private static function t(string $text): array
    {
        return ['type' => 'text', 'text' => $text];
    }

    private static function b(string $text): array
    {
        return ['type' => 'text', 'text' => $text, 'marks' => [['type' => 'bold']]];
    }

    private static function i(string $text): array
    {
        return ['type' => 'text', 'text' => $text, 'marks' => [['type' => 'italic']]];
    }

    private static function link(string $text, string $href): array
    {
        return ['type' => 'text', 'text' => $text, 'marks' => [['type' => 'link', 'attrs' => ['href' => $href]]]];
    }
}
