<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Album;
use App\Entity\Review;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create Admin User
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setUsername('admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        // Create Moderator User
        $moderator = new User();
        $moderator->setEmail('mod@example.com');
        $moderator->setUsername('moderator');
        $moderator->setRoles(['ROLE_MODERATOR']);
        $moderator->setPassword($this->passwordHasher->hashPassword($moderator, 'mod123'));
        $manager->persist($moderator);

        // Create Regular Users
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $user = new User();
            $user->setEmail("user{$i}@example.com");
            $user->setUsername("user{$i}");
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
            $manager->persist($user);
            $users[] = $user;
        }

        // Sample Albums Data
        $albumsData = [
            ['Dark Side of the Moon', 'Pink Floyd', 'Rock', 1973],
            ['Thriller', 'Michael Jackson', 'Pop', 1982],
            ['Abbey Road', 'The Beatles', 'Rock', 1969],
            ['The Chronic', 'Dr. Dre', 'Hip Hop', 1992],
            ['Kind of Blue', 'Miles Davis', 'Jazz', 1959],
            ['Nevermind', 'Nirvana', 'Rock', 1991],
            ['Rumours', 'Fleetwood Mac', 'Rock', 1977],
            ['Born to Run', 'Bruce Springsteen', 'Rock', 1975],
        ];

        $albums = [];
        foreach ($albumsData as $albumData) {
            $album = new Album();
            $album->setTitle($albumData[0]);
            $album->setArtist($albumData[1]);
            $album->setGenre($albumData[2]);
            $album->setReleaseYear($albumData[3]);
            $album->setCreatedBy($users[array_rand($users)]);
            $album->setTrackList("Track 1\nTrack 2\nTrack 3\nTrack 4\nTrack 5");
            $manager->persist($album);
            $albums[] = $album;
        }



        // Create sample built in Reviews
        $reviewTexts = [
            "This album is an absolute masterpiece. The production quality is exceptional and every track flows perfectly into the next. A timeless classic that deserves all the praise it gets.",
            "I've listened to this album countless times and it never gets old. The songwriting is brilliant and the performances are outstanding. Highly recommended for any music lover.",
            "While this album has some great moments, it feels a bit inconsistent. Some tracks are amazing while others fall flat. Still worth a listen though.",
            "A groundbreaking album that defined a generation. The innovation and creativity on display here is simply incredible. This is essential listening.",
            "Solid album with excellent musicianship throughout. Not every track is a hit, but the overall quality is very high. Definitely worth checking out.",
        ];

        //sample review titles
        $reviewTitles = [
            "Absolutely Amazing",
            "A True Classic",
            "Essential Listening",
            "Masterpiece",
            "Highly Recommended",
            "Brilliant Album",
            "Outstanding Work",
            "Incredible Music",
        ];

        $totalReviews = 0;
        foreach ($albums as $album) {
            $numReviews = rand(2, 4);
            $reviewers = $users;
            shuffle($reviewers);

            for ($i = 0; $i < $numReviews && $i < count($reviewers); $i++) {
                $review = new Review();
                $review->setAlbum($album);
                $review->setUser($reviewers[$i]);
                $review->setRating(rand(7, 10));
                $review->setContent($reviewTexts[array_rand($reviewTexts)]);
                $review->setTitle($reviewTitles[array_rand($reviewTitles)]);
                $manager->persist($review);
                $totalReviews++;
            }
        }

        echo "✓ Created {$totalReviews} reviews\n";

        // Save everything to database
        $manager->flush();

        echo "\n";
        echo "========================================\n";
        echo "✅ Sample Data Loaded Successfully!\n";
        echo "========================================\n";
        echo "Users: " . count($users) . "\n";
        echo "Albums: " . count($albums) . "\n";
        echo "Reviews: {$totalReviews}\n";
        echo "\n";
        echo "Login Credentials:\n";
        echo "  Admin: admin@example.com / admin123\n";
        echo "  Moderator: moderator@example.com / mod123\n";
        echo "  User: user1@example.com / password\n";
        echo "========================================\n";
    }
}
