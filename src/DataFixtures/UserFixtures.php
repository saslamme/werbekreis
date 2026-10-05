<?php
declare(strict_types=1);
namespace App\DataFixtures;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
/** Development/test data only. Never load into a production database. */
final class UserFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher) {}
    public function load(ObjectManager $manager): void
    {
        foreach ([['admin', 'Admin', 'ROLE_ADMIN'], ['editor', 'Editor', 'ROLE_EDITOR'], ['member', 'Member', 'ROLE_MEMBER']] as [$login, $firstName, $role]) {
            $user = (new User())->setEmail($login.'@example.local')->setFirstName($firstName)->setLastName('User')->setRoles([$role]);
            $user->setPassword($this->hasher->hashPassword($user, $login.'123'));
            $manager->persist($user);
        }
        $manager->flush();
    }
}
