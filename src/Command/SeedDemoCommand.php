<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Event;
use App\Entity\Follow;
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

/**
 * Seeds car-themed demo content: 5 teams (each with a matching banner/profile
 * car photo), 12 themed events, plus members, team-follows, event-joins and
 * image posts spread across the existing users.
 *
 * Run inside the container so it writes to the same DB and uploads volume the
 * app uses:
 *
 *   docker cp docker/test_data/car_images php:/tmp/car_images
 *   docker exec php php bin/console app:seed-demo
 */
#[AsCommand(name: 'app:seed-demo', description: 'Seed car-themed teams, events and posts onto existing users')]
class SeedDemoCommand extends Command
{
    /**
     * Five car-club themes. `keywords` are matched against the car-image
     * filenames so each team/event/post gets an on-theme photo.
     *
     * @var array<int, array{name: string, keywords: string[], description: string, events: array<int, array{name: string, location: string, inDays: int, hours: int, description: string}>, captions: string[]}>
     */
    private const THEMES = [
        [
            'name' => 'Bavarian Bois DK',
            'keywords' => ['bmw'],
            'description' => "Denmark's tightest BMW M crew. From E30 classics to the latest G8x, if it wears the roundel and a wide track, you belong here. We meet for canyon runs, track days and a lot of talk about diff ratios.",
            'events' => [
                ['name' => 'M Power Track Day', 'location' => 'FDM Sjællandsringen, Roskilde', 'inDays' => 12, 'hours' => 8, 'description' => 'A full day of lapping for everything with an M badge. Helmets mandatory, slicks optional.'],
                ['name' => 'Roundel Sunday Coffee & Cars', 'location' => 'Amager Strandpark, København', 'inDays' => 5, 'hours' => 3, 'description' => 'Chilled morning meet. Bring your M car, grab a coffee, talk shop.'],
                ['name' => 'E30 vs Gxx Cruise Night', 'location' => 'Bryggen, København', 'inDays' => 26, 'hours' => 4, 'description' => 'Old school meets new school for an evening cruise through the city.'],
            ],
            'captions' => [
                'S58 is finally dialled in. The way this thing pulls past 5k is criminal.',
                'Nothing sounds like a straight six at full chat. E46 forever.',
                'New wheels mounted for the season. Concave for days.',
                'Cold start this morning. The M division knows what they are doing.',
            ],
        ],
        [
            'name' => 'Stuttgart Syndicate',
            'keywords' => ['porsche', 'mercedes', 'benz'],
            'description' => 'Porsche and Mercedes-AMG owners united by flat-sixes and hand-built V8s. We are about precision, heritage and getting cars out of the garage and onto the road where they belong.',
            'events' => [
                ['name' => 'Flat-Six Breakfast Run', 'location' => 'Dyrehaven, Klampenborg', 'inDays' => 7, 'hours' => 4, 'description' => 'Early breakfast then a spirited run through the forest roads north of the city.'],
                ['name' => 'AMG Night at the Docks', 'location' => 'Nordhavn, København', 'inDays' => 19, 'hours' => 3, 'description' => 'V8 idle therapy by the water. Bring earplugs.'],
                ['name' => 'Stuttgart Concours Meet', 'location' => 'Aarhus Havn', 'inDays' => 33, 'hours' => 5, 'description' => 'Show-and-shine for the cleanest Porsches and Benzes in Jutland.'],
            ],
            'captions' => [
                'Took the 911 out before sunrise. Empty roads, flat-six soundtrack, perfect.',
                'Hand-built AMG V8. You can feel every one of those horses.',
                'Freshly detailed and ready for the meet. Stuttgart steel hits different.',
                'PDK or manual, the debate never ends. I will take both.',
            ],
        ],
        [
            'name' => 'Prancing Horse Danmark',
            'keywords' => ['ferrari'],
            'description' => 'For the tifosi of Denmark. Ferrari owners and superfans gathering to celebrate Maranello red. Track days, scenic drives, and an unhealthy obsession with naturally aspirated V8s and V12s.',
            'events' => [
                ['name' => 'Rosso Corsa Drive Day', 'location' => 'Møns Klint', 'inDays' => 15, 'hours' => 6, 'description' => 'A scenic convoy to the cliffs with a lunch stop. Red cars preferred but all welcome.'],
                ['name' => 'Maranello Meet & Greet', 'location' => 'Tivoli, København', 'inDays' => 40, 'hours' => 4, 'description' => 'Static display in the heart of the city. Come talk Ferrari history.'],
            ],
            'captions' => [
                'The 458 might be the last great naturally aspirated V8. Goosebumps every time.',
                'Rosso Corsa in the sun. There is no better colour.',
                'That V12 wail echoing off the buildings. Pure theatre.',
                'Cleaned every spoke by hand. Worth it for a horse like this.',
            ],
        ],
        [
            'name' => 'Raging Bull Nordic',
            'keywords' => ['lamborghini', 'aventador', 'huracan'],
            'description' => 'Lamborghini lovers from across the Nordics. Loud, angular and unapologetic. We organise convoys, charity drives and the occasional dyno day where the only goal is more noise.',
            'events' => [
                ['name' => 'Bull Run Convoy', 'location' => 'Storebæltsbroen', 'inDays' => 22, 'hours' => 5, 'description' => 'A roaring convoy across the bridge. Be there for the launch.'],
                ['name' => 'Sant\'Agata Dyno Day', 'location' => 'Padborg', 'inDays' => 48, 'hours' => 7, 'description' => 'Strap in and find out what your bull really makes. Numbers and noise all day.'],
            ],
            'captions' => [
                'Scissor doors up, neighbours awake. Aventador mornings.',
                'The Huracan V10 at 8000rpm is a religious experience.',
                'Angular, loud and absolutely ridiculous. Exactly how I like it.',
                'Convoy season is back. See you on the bridge.',
            ],
        ],
        [
            'name' => 'American Muscle København',
            'keywords' => ['mustang', 'camaro', 'shelby', 'chevrolet', 'ford'],
            'description' => 'Big V8s, burnouts and Americana in the heart of Denmark. Mustangs, Camaros, Shelbys and everything with a pushrod heart. We keep it loud, low and proud.',
            'events' => [
                ['name' => 'Muscle & Burgers Cruise', 'location' => 'Roskilde Dyreskueplads', 'inDays' => 9, 'hours' => 4, 'description' => 'Classic American cruise-in. Burgers, V8s and good vibes.'],
                ['name' => 'Pony Car Quarter Mile', 'location' => 'Vandel Dragstrip', 'inDays' => 30, 'hours' => 8, 'description' => 'Run what you brought down the strip. Bracket racing all afternoon.'],
            ],
            'captions' => [
                'Nothing wakes the street like a cold-start V8 burble.',
                'Stang got new shoes and a tune. She is ready to misbehave.',
                'Camaro therapy after a long week. Just me and the rumble.',
                'Burnout box was calling. The tyres answered.',
            ],
        ],
    ];

    /** Generic car captions used as a fallback for variety. */
    private const GENERIC_CAPTIONS = [
        'Sunday meet was unreal. So many clean builds in one place.',
        'Detailed all weekend, now just waiting for the golden hour shots.',
        'This is why we wrench. Worth every late night in the garage.',
        'Met some legends at the event today. This community is the best.',
        'New mods on, dialled in, and grinning ear to ear.',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
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
        $this->addOption(
            'images',
            null,
            InputOption::VALUE_REQUIRED,
            'Directory containing the source car images',
            '/tmp/car_images',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $imagesDir = (string) $input->getOption('images');
        $pool = glob(rtrim($imagesDir, '/').'/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
        if (!$pool) {
            $io->error("No car images found in {$imagesDir}. Copy them in first, e.g.: docker cp docker/test_data/car_images php:/tmp/car_images");

            return Command::FAILURE;
        }
        $io->writeln(sprintf('Found %d car images in %s', count($pool), $imagesDir));

        /** @var User[] $users */
        $users = $this->em->getRepository(User::class)->findBy(['isDeleted' => false]);
        shuffle($users);
        if (count($users) < 25) {
            $io->error('Need at least 25 users to seed demo content; found '.count($users).'.');

            return Command::FAILURE;
        }

        $owners = array_splice($users, 0, 5);
        $members = array_splice($users, 0, 20);
        $rest = $users; // everyone left

        // ── Teams (banner + profile = the same themed car photo) ──────────────
        /** @var array<int, Team> $teams */
        $teams = [];
        foreach (self::THEMES as $i => $theme) {
            $team = new Team();
            $team->setName($theme['name']);
            $team->setDescription($theme['description']);

            $source = $this->pickImage($pool, $theme['keywords']);
            $team->setBannerFilename($this->placeImage($source, $this->teamBannersDir));
            $team->setProfilePictureFilename($this->placeImage($source, $this->teamProfileDir));

            $this->em->persist($team);
            $this->em->persist(new TeamMember($team, $owners[$i], TeamRole::Owner));
            $teams[$i] = $team;
        }
        $io->writeln('Created '.count($teams).' teams with owners.');

        // ── 20 members spread across 1–2 teams each ───────────────────────────
        $memberLinks = 0;
        foreach ($members as $member) {
            foreach ($this->pickSome(array_keys($teams), 1, 2) as $ti) {
                $this->em->persist(new TeamMember($teams[$ti], $member, TeamRole::Member));
                ++$memberLinks;
            }
        }
        $io->writeln("Added {$memberLinks} team memberships across 20 users.");

        // ── 12 themed events, hosted by each team's owner ─────────────────────
        /** @var array<int, array{event: Event, theme: int}> $events */
        $events = [];
        foreach (self::THEMES as $i => $theme) {
            foreach ($theme['events'] as $spec) {
                $event = new Event($owners[$i]);
                $event->setTeam($teams[$i]);
                $event->setName($spec['name']);
                $event->setDescription($spec['description']);
                $event->setLocation($spec['location']);
                $event->setTimezone('Europe/Copenhagen');

                $start = new \DateTime('+'.$spec['inDays'].' days');
                $start->setTime(mt_rand(9, 18), [0, 15, 30, 45][array_rand([0, 15, 30, 45])]);
                $event->setStartDate($start);
                $end = (clone $start)->modify('+'.$spec['hours'].' hours');
                $event->setEndDate($end);

                $event->setImageFilename($this->placeImage($this->pickImage($pool, $theme['keywords']), $this->eventBannersDir));

                $this->em->persist($event);
                $events[] = ['event' => $event, 'theme' => $i];
            }
        }
        $io->writeln('Created '.count($events).' themed events.');

        // ── Remaining users: follow teams, join events, post images ───────────
        $follows = 0;
        $participations = 0;
        $posts = 0;
        foreach ($rest as $user) {
            foreach ($this->pickSome(array_keys($teams), 1, 3) as $ti) {
                $this->em->persist(Follow::forTeam($user, $teams[$ti]));
                ++$follows;
            }

            $joinedEvents = $this->pickSome(array_keys($events), 1, 3);
            foreach ($joinedEvents as $ei) {
                $status = mt_rand(0, 4) === 0 ? ParticipationStatus::Maybe : ParticipationStatus::Going;
                $this->em->persist(new Participation($events[$ei]['event'], $user, $status));
                ++$participations;
            }

            // Post into an event they joined (fall back to any event).
            foreach ($this->pickSome($joinedEvents ?: array_keys($events), 1, 2) as $ei) {
                $entry = $events[$ei];
                $theme = self::THEMES[$entry['theme']];

                $post = new Post($entry['event'], $user);
                $post->setBody($this->pickCaption($theme['captions']));

                $img = $this->placeImage($this->pickImage($pool, $theme['keywords']), $this->eventFeedDir);
                $post->addImage(new PostImage($post, $img, 0));

                $this->em->persist($post);
                ++$posts;
            }
        }

        $this->em->flush();

        $io->success(sprintf(
            'Seeded: %d teams, %d memberships, %d events, %d team-follows, %d event-joins, %d posts.',
            count($teams),
            $memberLinks,
            count($events),
            $follows,
            $participations,
            $posts,
        ));

        return Command::SUCCESS;
    }

    /** Pick a random source image whose filename matches any keyword (else any). */
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

    /** Copy a source image into an upload dir under a fresh unique name; return that name. */
    private function placeImage(string $source, string $destDir): string
    {
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }

        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg');
        $base = preg_replace('/[^a-z0-9]+/i', '-', pathinfo($source, PATHINFO_FILENAME)) ?? 'car';
        $base = substr(trim($base, '-'), 0, 60);
        $name = $base.'-'.uniqid().'.'.$ext;

        copy($source, rtrim($destDir, '/').'/'.$name);

        return $name;
    }

    /**
     * Return between $min and $max distinct random values from $items.
     *
     * @template T
     * @param array<int, T> $items
     * @return array<int, T>
     */
    private function pickSome(array $items, int $min, int $max): array
    {
        if (!$items) {
            return [];
        }
        $count = min(count($items), mt_rand($min, $max));
        $keys = (array) array_rand($items, $count);

        return array_map(static fn ($k) => $items[$k], $keys);
    }

    private function pickCaption(array $themeCaptions): string
    {
        $pool = array_merge($themeCaptions, self::GENERIC_CAPTIONS);

        return $pool[array_rand($pool)];
    }
}
