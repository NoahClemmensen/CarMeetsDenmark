<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Event;
use App\Entity\Participation;
use App\Entity\Post;
use App\Entity\PostImage;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\ParticipationStatus;
use App\Enum\TeamRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Full demo seeder matching the requested flow end-to-end:
 *
 *   1. Create N fake users (default 100), password "admin1234", each with a
 *      profile picture from the faces folder.
 *   2. Create 5 themed car-club teams (banner + profile from car images),
 *      each with 5-10 members.
 *   3. Create 3 events per team (15 total), each with a car image.
 *   4. Per event, record who is going / maybe / not going (Participation) and
 *      create 10-20 posts whose text reflects that attendance, each with a
 *      car image.
 *
 * Source images are baked into the container at /var/www/docker/test_data,
 * so it runs without any docker cp:
 *
 *   docker exec php php bin/console app:seed-demo-full
 */
#[AsCommand(name: 'app:seed-demo-full', description: 'Create users with avatars, then teams/events/posts with car images')]
class SeedFullDemoCommand extends Command
{
    private const FIRST_NAMES = [
        'Magnus', 'Frederik', 'Mathias', 'Mikkel', 'Lucas', 'Oliver', 'Emil', 'Victor',
        'William', 'Noah', 'Oscar', 'Alexander', 'Sebastian', 'Christian', 'Jacob',
        'Sofie', 'Freja', 'Clara', 'Laura', 'Ida', 'Anna', 'Emma', 'Josefine', 'Caroline',
        'Maja', 'Mathilde', 'Julie', 'Cecilie', 'Camilla', 'Nanna', 'Sara', 'Amalie',
        'Tobias', 'Rasmus', 'Anders', 'Daniel', 'Nikolaj', 'Simon', 'Aksel', 'Elias',
    ];

    private const LAST_NAMES = [
        'Nielsen', 'Jensen', 'Hansen', 'Pedersen', 'Andersen', 'Christensen', 'Larsen',
        'Sorensen', 'Rasmussen', 'Jorgensen', 'Petersen', 'Madsen', 'Kristensen', 'Olsen',
        'Thomsen', 'Christiansen', 'Poulsen', 'Johansen', 'Moller', 'Mortensen', 'Knudsen',
        'Holm', 'Lund', 'Schmidt', 'Berg', 'Kjaer', 'Vestergaard', 'Damgaard', 'Bruun',
    ];

    /**
     * @var array<int, array{name: string, keywords: string[], description: string, events: array<int, array{name: string, location: string, inDays: int, hours: int, description: string}>, captions: string[]}>
     */
    private const THEMES = [
        [
            'name' => 'Bavarian Bois DK',
            'keywords' => ['bmw'],
            'description' => "Denmark's tightest BMW M crew. From E30 classics to the latest G8x, if it wears the roundel and a wide track, you belong here.",
            'events' => [
                ['name' => 'M Power Track Day', 'location' => 'FDM Sjaellandsringen, Roskilde', 'inDays' => 12, 'hours' => 8, 'description' => 'A full day of lapping for everything with an M badge. Helmets mandatory, slicks optional.'],
                ['name' => 'Roundel Sunday Coffee & Cars', 'location' => 'Amager Strandpark, Kobenhavn', 'inDays' => 5, 'hours' => 3, 'description' => 'Chilled morning meet. Bring your M car, grab a coffee, talk shop.'],
                ['name' => 'E30 vs Gxx Cruise Night', 'location' => 'Bryggen, Kobenhavn', 'inDays' => 26, 'hours' => 4, 'description' => 'Old school meets new school for an evening cruise through the city.'],
            ],
            'captions' => [
                'S58 is finally dialled in. The way this thing pulls past 5k is criminal.',
                'Nothing sounds like a straight six at full chat. E46 forever.',
                'New wheels mounted for the season. Concave for days.',
            ],
        ],
        [
            'name' => 'Stuttgart Syndicate',
            'keywords' => ['porsche', 'mercedes', 'benz'],
            'description' => 'Porsche and Mercedes-AMG owners united by flat-sixes and hand-built V8s. Precision, heritage, and cars on the road where they belong.',
            'events' => [
                ['name' => 'Flat-Six Breakfast Run', 'location' => 'Dyrehaven, Klampenborg', 'inDays' => 7, 'hours' => 4, 'description' => 'Early breakfast then a spirited run through the forest roads north of the city.'],
                ['name' => 'AMG Night at the Docks', 'location' => 'Nordhavn, Kobenhavn', 'inDays' => 19, 'hours' => 3, 'description' => 'V8 idle therapy by the water. Bring earplugs.'],
                ['name' => 'Stuttgart Concours Meet', 'location' => 'Aarhus Havn', 'inDays' => 33, 'hours' => 5, 'description' => 'Show-and-shine for the cleanest Porsches and Benzes in Jutland.'],
            ],
            'captions' => [
                'Took the 911 out before sunrise. Empty roads, flat-six soundtrack, perfect.',
                'Hand-built AMG V8. You can feel every one of those horses.',
                'PDK or manual, the debate never ends. I will take both.',
            ],
        ],
        [
            'name' => 'Prancing Horse Danmark',
            'keywords' => ['ferrari'],
            'description' => 'For the tifosi of Denmark. Ferrari owners and superfans celebrating Maranello red. Track days, scenic drives, and naturally aspirated V8s and V12s.',
            'events' => [
                ['name' => 'Rosso Corsa Drive Day', 'location' => 'Mons Klint', 'inDays' => 15, 'hours' => 6, 'description' => 'A scenic convoy to the cliffs with a lunch stop. Red cars preferred but all welcome.'],
                ['name' => 'Maranello Meet & Greet', 'location' => 'Tivoli, Kobenhavn', 'inDays' => 40, 'hours' => 4, 'description' => 'Static display in the heart of the city. Come talk Ferrari history.'],
                ['name' => 'Cavallino Night Cruise', 'location' => 'Langelinie, Kobenhavn', 'inDays' => 52, 'hours' => 3, 'description' => 'Evening cruise along the harbour. Headlights up, V8s singing.'],
            ],
            'captions' => [
                'The 458 might be the last great naturally aspirated V8. Goosebumps every time.',
                'Rosso Corsa in the sun. There is no better colour.',
                'Cleaned every spoke by hand. Worth it for a horse like this.',
            ],
        ],
        [
            'name' => 'Raging Bull Nordic',
            'keywords' => ['lamborghini', 'aventador', 'huracan', 'super'],
            'description' => 'Lamborghini lovers from across the Nordics. Loud, angular and unapologetic. Convoys, charity drives and the occasional dyno day.',
            'events' => [
                ['name' => 'Bull Run Convoy', 'location' => 'Storebaeltsbroen', 'inDays' => 22, 'hours' => 5, 'description' => 'A roaring convoy across the bridge. Be there for the launch.'],
                ['name' => 'SantAgata Dyno Day', 'location' => 'Padborg', 'inDays' => 48, 'hours' => 7, 'description' => 'Strap in and find out what your bull really makes. Numbers and noise all day.'],
                ['name' => 'Scissor Door Sunday', 'location' => 'Fisketorvet, Kobenhavn', 'inDays' => 11, 'hours' => 4, 'description' => 'Static meet in the city. Doors up, crowds gathering.'],
            ],
            'captions' => [
                'Scissor doors up, neighbours awake. Aventador mornings.',
                'The Huracan V10 at 8000rpm is a religious experience.',
                'Convoy season is back. See you on the bridge.',
            ],
        ],
        [
            'name' => 'American Muscle Kobenhavn',
            'keywords' => ['mustang', 'camaro', 'shelby', 'chevrolet', 'ford'],
            'description' => 'Big V8s, burnouts and Americana in the heart of Denmark. Mustangs, Camaros, Shelbys and everything with a pushrod heart.',
            'events' => [
                ['name' => 'Muscle & Burgers Cruise', 'location' => 'Roskilde Dyreskueplads', 'inDays' => 9, 'hours' => 4, 'description' => 'Classic American cruise-in. Burgers, V8s and good vibes.'],
                ['name' => 'Pony Car Quarter Mile', 'location' => 'Vandel Dragstrip', 'inDays' => 30, 'hours' => 8, 'description' => 'Run what you brought down the strip. Bracket racing all afternoon.'],
                ['name' => 'V8 Rumble Meet', 'location' => 'Helsingor Havn', 'inDays' => 18, 'hours' => 3, 'description' => 'Coast-side meet with nothing but American iron and loud exhausts.'],
            ],
            'captions' => [
                'Nothing wakes the street like a cold-start V8 burble.',
                'Stang got new shoes and a tune. She is ready to misbehave.',
                'Burnout box was calling. The tyres answered.',
            ],
        ],
    ];

    private const GOING_LINES = [
        'Count me in! Already cleaned the car and topped up the tank.',
        'I am 100% going. Wouldnt miss this for anything.',
        'Locked in. See you all there, bringing the good camera too.',
        'Going for sure. Save me a parking spot up front.',
        'Definitely attending. Been looking forward to this all month.',
    ];

    private const MAYBE_LINES = [
        'Might swing by if work lets me off early. Tentative yes.',
        'Maybe? Depends on the weather, but I really want to come.',
        'On the fence but leaning towards going. Will confirm soon.',
    ];

    private const NOT_GOING_LINES = [
        'Gutted, but I cant make it this time. Have fun everyone!',
        'Not going to make it, family stuff that weekend. Next one for sure.',
        'Sitting this one out, car is mid-build. Post lots of photos!',
        'Cant come unfortunately, already double booked. Enjoy it!',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        #[Autowire('%user_avatars_directory%')]
        private readonly string $avatarsDir,
        #[Autowire('%team_banners_directory%')]
        private readonly string $teamBannersDir,
        #[Autowire('%team_profile_pictures_directory%')]
        private readonly string $teamProfileDir,
        #[Autowire('%event_banners_directory%')]
        private readonly string $eventBannersDir,
        #[Autowire('%event_feed_directory%')]
        private readonly string $eventFeedDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('users', null, InputOption::VALUE_REQUIRED, 'How many users to create', '100')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password for every created user', 'admin1234')
            ->addOption('faces', null, InputOption::VALUE_REQUIRED, 'Directory of face images', '/var/www/docker/test_data/faces')
            ->addOption('images', null, InputOption::VALUE_REQUIRED, 'Directory of car images', '/var/www/docker/test_data/car_images');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $userCount = max(25, (int) $input->getOption('users'));
        $password = (string) $input->getOption('password');
        $faces = $this->imagePool((string) $input->getOption('faces'));
        $cars = $this->imagePool((string) $input->getOption('images'));

        if (!$faces) {
            $io->error('No face images found.');

            return Command::FAILURE;
        }
        if (!$cars) {
            $io->error('No car images found.');

            return Command::FAILURE;
        }
        $io->writeln(sprintf('Found %d faces and %d car images.', count($faces), count($cars)));

        // ── 1. Users with avatars ────────────────────────────────────────────
        $existingEmails = array_flip(array_map(
            static fn (array $r): string => (string) $r['email'],
            $this->em->getConnection()->fetchAllAssociative('SELECT email FROM `user`'),
        ));

        /** @var User[] $users */
        $users = [];
        for ($i = 0; $i < $userCount; ++$i) {
            $first = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
            $last = self::LAST_NAMES[array_rand(self::LAST_NAMES)];

            $email = strtolower($first.'.'.$last.($i + 1).'@carmeets.test');
            while (isset($existingEmails[$email])) {
                $email = strtolower($first.'.'.$last.($i + 1).'.'.bin2hex(random_bytes(2)).'@carmeets.test');
            }
            $existingEmails[$email] = true;

            $user = new User();
            $user->setName($first.' '.$last);
            $user->setEmail($email);
            $user->setPassword($this->hasher->hashPassword($user, $password));
            $user->setIsVerified(true);
            $user->setTimezone('Europe/Copenhagen');
            $user->setLanguage('en');
            $user->setAvatarFilename($this->placeImage($faces[array_rand($faces)], $this->avatarsDir));

            $this->em->persist($user);
            $users[] = $user;

            if (($i + 1) % 50 === 0) {
                $this->em->flush();
            }
        }
        $this->em->flush();
        $io->writeln(sprintf('Created %d users (password "%s") with profile pictures.', count($users), $password));

        // ── 2. Teams with 5-10 members ───────────────────────────────────────
        /** @var array<int, array{team: Team, members: User[]}> $teams */
        $teams = [];
        foreach (self::THEMES as $i => $theme) {
            $team = new Team();
            $team->setName($theme['name']);
            $team->setDescription($theme['description']);

            $banner = $this->pickImage($cars, $theme['keywords']);
            $team->setBannerFilename($this->placeImage($banner, $this->teamBannersDir));
            $team->setProfilePictureFilename($this->placeImage($this->pickImage($cars, $theme['keywords']), $this->teamProfileDir));
            $this->em->persist($team);

            $memberCount = random_int(5, 10);
            $members = $this->sample($users, $memberCount);
            foreach ($members as $idx => $member) {
                $role = $idx === 0 ? TeamRole::Owner : TeamRole::Member;
                $this->em->persist(new TeamMember($team, $member, $role));
            }

            $teams[$i] = ['team' => $team, 'members' => $members];
        }
        $this->em->flush();
        $io->writeln(sprintf(
            'Created %d teams with %s members each.',
            count($teams),
            implode('/', array_map(static fn ($t) => count($t['members']), $teams)),
        ));

        // ── 3. Events (3 per team) + 4. Participation & posts ─────────────────
        $eventTotal = 0;
        $participationTotal = 0;
        $postTotal = 0;

        foreach ($teams as $i => $entry) {
            $theme = self::THEMES[$i];
            $team = $entry['team'];
            $members = $entry['members'];
            $owner = $members[0];

            foreach (array_slice($theme['events'], 0, 3) as $spec) {
                $event = new Event($owner);
                $event->setTeam($team);
                $event->setName($spec['name']);
                $event->setDescription($spec['description']);
                $event->setLocation($spec['location']);
                $event->setTimezone('Europe/Copenhagen');

                $start = new \DateTime('+'.$spec['inDays'].' days');
                $start->setTime(random_int(9, 18), [0, 15, 30, 45][array_rand([0, 15, 30, 45])]);
                $event->setStartDate($start);
                $event->setEndDate((clone $start)->modify('+'.$spec['hours'].' hours'));
                $event->setImageFilename($this->placeImage($this->pickImage($cars, $theme['keywords']), $this->eventBannersDir));
                $this->em->persist($event);
                ++$eventTotal;

                // Who is going / maybe / not going (one row per member).
                /** @var array{going: User[], maybe: User[], declined: User[]} $attendance */
                $attendance = ['going' => [], 'maybe' => [], 'declined' => []];
                foreach ($members as $member) {
                    $roll = random_int(1, 100);
                    if ($roll <= 60) {
                        $status = ParticipationStatus::Going;
                        $attendance['going'][] = $member;
                    } elseif ($roll <= 75) {
                        $status = ParticipationStatus::Maybe;
                        $attendance['maybe'][] = $member;
                    } else {
                        $status = ParticipationStatus::Declined;
                        $attendance['declined'][] = $member;
                    }
                    $this->em->persist(new Participation($event, $member, $status));
                    ++$participationTotal;
                }
                $event->setHypeCount(count($attendance['going']));

                // 10-20 posts whose text reflects the author's attendance.
                $postCount = random_int(10, 20);
                for ($p = 0; $p < $postCount; ++$p) {
                    [$author, $bucket] = $this->pickAuthor($attendance, $members);

                    $post = new Post($event, $author);
                    $post->setBody($this->bodyFor($bucket, $theme['captions']));
                    $post->setHypeCount(random_int(0, 25));
                    // Spread post times over the last few days for a realistic feed.
                    $this->backdate($post, time() - random_int(0, 6 * 24 * 3600));
                    $post->addImage(new PostImage($post, $this->placeImage($this->pickImage($cars, $theme['keywords']), $this->eventFeedDir), 0));
                    $this->em->persist($post);
                    ++$postTotal;
                }
            }

            // Flush per team to keep memory bounded. No em->clear() here: the
            // $teams/$users entities are reused by later iterations and must
            // stay managed.
            $this->em->flush();
        }

        $io->success(sprintf(
            'Seeded: %d users, %d teams, %d events, %d participations, %d posts.',
            count($users),
            count($teams),
            $eventTotal,
            $participationTotal,
            $postTotal,
        ));

        return Command::SUCCESS;
    }

    /** @param array{going: User[], maybe: User[], declined: User[]} $attendance
     * @param User[] $members
     * @return array{0: User, 1: string} */
    private function pickAuthor(array $attendance, array $members): array
    {
        // Weight posters towards people who are going, but let everyone speak.
        $roll = random_int(1, 100);
        $order = $roll <= 65 ? ['going', 'maybe', 'declined'] : ($roll <= 85 ? ['declined', 'going', 'maybe'] : ['maybe', 'going', 'declined']);
        foreach ($order as $bucket) {
            if ($attendance[$bucket]) {
                return [$attendance[$bucket][array_rand($attendance[$bucket])], $bucket];
            }
        }

        return [$members[array_rand($members)], 'going'];
    }

    /** @param string[] $captions */
    private function bodyFor(string $bucket, array $captions): string
    {
        $line = match ($bucket) {
            'going' => self::GOING_LINES[array_rand(self::GOING_LINES)],
            'maybe' => self::MAYBE_LINES[array_rand(self::MAYBE_LINES)],
            default => self::NOT_GOING_LINES[array_rand(self::NOT_GOING_LINES)],
        };

        // Half the time add a car-themed caption for flavour.
        if (random_int(0, 1) === 1) {
            $line .= ' '.$captions[array_rand($captions)];
        }

        return $line;
    }

    /** Post::createdAt is set to time() in the constructor; backdate via reflection. */
    private function backdate(Post $post, int $timestamp): void
    {
        $ref = new \ReflectionProperty(Post::class, 'createdAt');
        $ref->setValue($post, $timestamp);
    }

    /** @return string[] */
    private function imagePool(string $dir): array
    {
        return glob(rtrim($dir, '/').'/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
    }

    /**
     * @param string[] $pool
     * @param string[] $keywords
     */
    private function pickImage(array $pool, array $keywords): string
    {
        $matches = array_values(array_filter($pool, static function (string $file) use ($keywords): bool {
            $name = strtolower(basename($file));
            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) {
                    return true;
                }
            }

            return false;
        }));

        $list = $matches ?: $pool;

        return $list[array_rand($list)];
    }

    private function placeImage(string $source, string $destDir): string
    {
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }

        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg');
        $base = preg_replace('/[^a-z0-9]+/i', '-', pathinfo($source, PATHINFO_FILENAME)) ?? 'img';
        $base = substr(trim((string) $base, '-'), 0, 60);
        $name = $base.'-'.bin2hex(random_bytes(6)).'.'.$ext;

        copy($source, rtrim($destDir, '/').'/'.$name);

        return $name;
    }

    /**
     * @param User[] $items
     * @return User[]
     */
    private function sample(array $items, int $count): array
    {
        $count = min($count, count($items));
        $keys = (array) array_rand($items, $count);
        shuffle($keys);

        return array_map(static fn ($k) => $items[$k], $keys);
    }
}
