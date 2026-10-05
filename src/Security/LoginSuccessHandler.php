<?php
declare(strict_types=1);
namespace App\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urls) {}
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $roles = $token->getRoleNames();
        $route = in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_EDITOR', $roles, true)
            ? 'admin_dashboard' : (in_array('ROLE_VOUCHER_REDEEMER', $roles, true) ? 'voucher_redeem' : (in_array('ROLE_MEMBER', $roles, true) ? 'member_dashboard' : 'app_home'));
        return new RedirectResponse($this->urls->generate($route));
    }
}
