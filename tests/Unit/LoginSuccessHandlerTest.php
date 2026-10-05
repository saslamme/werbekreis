<?php
declare(strict_types=1);
namespace App\Tests\Unit;
use App\Security\LoginSuccessHandler;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
final class LoginSuccessHandlerTest extends TestCase
{
    #[DataProvider('roles')]
    public function testRolePriority(array $roles, string $expected): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::once())->method('generate')->with($expected)->willReturn('/target');
        $token = $this->createMock(TokenInterface::class);
        $token->method('getRoleNames')->willReturn($roles);
        self::assertSame('/target', (new LoginSuccessHandler($urls))->onAuthenticationSuccess(new Request(), $token)->getTargetUrl());
    }
    public static function roles(): array
    {
        return [[['ROLE_MEMBER','ROLE_ADMIN'], 'admin_dashboard'], [['ROLE_MEMBER','ROLE_EDITOR'], 'admin_dashboard'], [['ROLE_MEMBER'], 'member_dashboard'], [[], 'app_home']];
    }
}
