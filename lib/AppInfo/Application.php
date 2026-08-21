<?php

declare(strict_types=1);

namespace OCA\EmployeeDashboard\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IDBConnection;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\L10N\IFactory;

/**
 * Registers the navigation entry only for members of an organization.
 *
 * Any membership row qualifies, whatever its role: an organization
 * administrator is also a person with tasks assigned to them, and this is their
 * personal view of that work — Admin Page answers a different question. What
 * the check does exclude is an account belonging to no organization at all,
 * for whom this dashboard has nothing to show.
 *
 * A direct query rather than a service call, because boot() runs on every
 * request and this decision needs one row.
 */
class Application extends App implements IBootstrap {

    public const APP_ID = 'employee_dashboard';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(function (
            INavigationManager $navigationManager,
            IUserSession $userSession,
            IDBConnection $db,
            IURLGenerator $urlGenerator,
            IFactory $l10nFactory,
        ): void {
            /**
            * The entry is registered unconditionally and decides for itself when the
            * navigation is actually built.
            *
            * boot() cannot do the check: on the OCS route the app menu is fetched from
            * (/ocs/v2.php/core/navigation/apps) apps are booted before the session is
            * resolved, so IUserSession::getUser() is null there and a check at boot
            * time hides the entry from everyone. A closure is evaluated later, from
            * NavigationManager::init(), by which point the user is known.
            *
            * Declining is expressed as a type other than 'link' because
            * INavigationManager::add() has no way to say "no entry" — it reads
            * $entry['id'] straight off the return value, so null would be fatal — and
            * getAll('link'), which is what builds the app menu, filters on exactly
            * that field.
            */
            $navigationManager->add(function () use ($userSession, $db, $urlGenerator, $l10nFactory): array {
                $entry = [
                    'id'    => self::APP_ID,
                    'order' => 11,
                    'href'  => $urlGenerator->linkToRoute(self::APP_ID . '.page.index'),
                    'icon'  => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
                    'name'  => $l10nFactory->get(self::APP_ID)->t('My Dashboard'),
                ];
                $user = $userSession->getUser();
                if ($user === null || !$this->belongsToOrg($db, $user->getUID())) {
                    $entry['type'] = 'hidden';
                }
                return $entry;
            });
        });
    }

    private function belongsToOrg(IDBConnection $db, string $uid): bool {
        $sql = "SELECT organization_id FROM *PREFIX*organization_members
                 WHERE user_uid = ? LIMIT 1";
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([$uid]);
            return $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            // The organization app may be absent on some instances; hiding the
            // button is the safer failure than linking to an empty dashboard.
            return false;
        }
    }
}
