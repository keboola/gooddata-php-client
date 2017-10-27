<?php
/**
 * @package gooddata-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\GoodData;

class Projects
{
    const ROLE_ADMIN = 'adminRole';
    const ROLE_EDITOR = 'editorRole';
    const ROLE_READ_ONLY = 'readOnlyUserRole';
    const ROLE_DASHBOARD_ONLY = 'dashboardOnlyRole';
    const ROLE_KEBOOLA_EDITOR = 'keboolaEditorPlusRole';

    public static $roles = [
        'admin' => self::ROLE_ADMIN,
        'editor' => self::ROLE_EDITOR,
        'readOnly' => self::ROLE_READ_ONLY,
        'dashboardOnly' => self::ROLE_DASHBOARD_ONLY,
        'keboolaEditor' => self::ROLE_KEBOOLA_EDITOR
    ];

    /** @var  Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public static function getPidFromUri($uri)
    {
        return substr($uri, strrpos($uri, '/')+1);
    }

    public static function getUriFromPid($pid)
    {
        return "/gdc/projects/$pid";
    }

    public function createProject($title, $authToken, $description = null, $testing = false, $driver = 'Pg')
    {
        $result = $this->client->post('/gdc/projects', [
            'project' => [
                'content' => [
                    'guidedNavigation' => 1,
                    'driver' => $driver,
                    'authorizationToken' => $authToken,
                    'environment' => $testing ? 'TESTING' : 'PRODUCTION'
                ],
                'meta' => [
                    'title' => $title,
                    'summary' => $description
                ]
            ]
        ]);

        if (empty($result['uri']) || strpos($result['uri'], '/gdc/projects/') === false) {
            throw Exception::unexpectedResponseError('Create project failed', 'POST', '/gdc/projects', $result);
        }

        // Wait until project is ready
        $projectUri = $result['uri'];
        $repeat = true;
        $try = 1;
        do {
            sleep(Client::WAIT_INTERVAL * ($try + 1));

            $result = $this->client->get($projectUri);
            if (isset($result['project']['content']['state']) && $result['project']['content']['state'] != 'DELETED') {
                if ($result['project']['content']['state'] == 'ENABLED') {
                    $repeat = false;
                }
            } else {
                throw Exception::unexpectedResponseError('Create project polling failed', 'GET', $projectUri, $result);
            }

            $try++;
        } while ($repeat);

        return self::getPidFromUri($projectUri);
    }

    public function getProject($pid)
    {
        return $this->client->get("/gdc/projects/$pid");
    }

    public function deleteProject($pid)
    {
        $this->client->delete("/gdc/projects/$pid");
    }

    public function getUsersForProjectYield($pid, $limit = 1000, $offset = 0)
    {
        $continue = true;
        do {
            $result = $this->client->get("/gdc/projects/$pid/users?offset=$offset&limit=$limit");
            if (count($result['users'])) {
                yield $result['users'];
            } else {
                $continue = false;
            }
            $offset += $limit;
        } while ($continue);
    }

    public function cloneProject($pidSource, $pidTarget, $includeData = 0, $includeUsers = 0)
    {
        $uri = "/gdc/md/$pidSource/maintenance/export";
        $params = [
            'exportProject' => [
                'exportUsers' => $includeUsers,
                'exportData' => $includeData
            ]
        ];
        $result = $this->client->post($uri, $params);
        if (empty($result['exportArtifact']['token']) || empty($result['exportArtifact']['status']['uri'])) {
            throw Exception::unexpectedResponseError('Clone project export failed', 'POST', $uri, $result);
        }

        $this->client->pollTask($result['exportArtifact']['status']['uri']);

        $uri = "/gdc/md/$pidTarget/maintenance/import";
        $result = $this->client->post($uri, [
            'importProject' => [
                'token' => $result['exportArtifact']['token']
            ]
        ]);
        if (empty($result['uri'])) {
            throw Exception::unexpectedResponseError('Clone project import failed', 'POST', $uri, $result);
        }

        $this->client->pollTask($result['uri']);
    }

    /**
     * @return bool | array
     */
    public function validate($pid, array $validate = ['pdm'])
    {
        $uri = "/gdc/md/$pid/validate";
        $result = $this->client->post($uri, ['validateProject' => $validate]);
        if (!isset($result['asyncTask']['link']['poll'])) {
            throw Exception::unexpectedResponseError('Project validation failed', 'POST', $uri, $result);
        }

        // Wait until validation is ready
        $repeat = true;
        $try = 1;
        do {
            sleep(Client::WAIT_INTERVAL * ($try + 1));

            $result = $this->client->get($result['asyncTask']['link']['poll']);
            if (isset($result['projectValidateResult'])) {
                return $result['projectValidateResult'];
            }

            $try++;
        } while ($repeat);

        return false;
    }

    public function getRoleUri($pid, $role)
    {
        $uri = "/gdc/projects/$pid/roles";
        $result = $this->client->get($uri);
        if (!isset($result['projectRoles']['roles'])) {
            throw Exception::unexpectedResponseError('Fetching roles in projects failed', 'GET', $uri, $result);
        }

        foreach ($result['projectRoles']['roles'] as $roleUri) {
            $roleResult = $this->client->get($roleUri);
            if (isset($roleResult['projectRole']['meta']['identifier'])
                && $roleResult['projectRole']['meta']['identifier'] == $role) {
                return $roleUri;
            }
        }

        throw Exception::error($uri, "Role '$role' not found in project");
    }

    public function isAccessible($pid)
    {
        $projectUri = self::getUriFromPid($pid);
        $uid = $this->client->getUsers()->getCurrentUid();
        foreach ($this->client->getUsers()->getProjectsYield($uid) as $projects) {
            foreach ($projects as $project) {
                if ($project['project']['links']['self'] == $projectUri) {
                    return true;
                }
            }
        }
        return false;
    }


    public function isAccessibleByUser($pid, $uid)
    {
        try {
            $this->client->get("/gdc/projects/$pid/users/$uid");
            return true;
        } catch (Exception $e) {
            if ($e->getCode() == 404 || $e->getCode() == 403) {
                return false;
            } else {
                throw $e;
            }
        }
    }

    public function addUser($pid, $uid, $role = self::ROLE_ADMIN)
    {
        $projectRoleUri = $this->getRoleUri($pid, $role);

        $uri = "/gdc/projects/$pid/users";
        $params = [
            'user' => [
                'content' => [
                    'status' => 'ENABLED',
                    'userRoles' => [$projectRoleUri]
                ],
                'links' => [
                    'self' => Users::getUriFromUid($uid)
                ]
            ]
        ];
        $result = $this->client->post($uri, $params);

        if ((isset($result['projectUsersUpdateResult']['successful'])
                && count($result['projectUsersUpdateResult']['successful']))
            || (isset($result['projectUsersUpdateResult']['failed'])
                && !count($result['projectUsersUpdateResult']['failed']))) {
            // SUCCESS
            // Sometimes API does not return
        } else {
            $errors = [];
            if (isset($result['projectUsersUpdateResult']['failed'])) {
                foreach ($result['projectUsersUpdateResult']['failed'] as $f) {
                    $errors[] = $f['message'];
                }
            }
            throw Exception::error($uri, 'Error in addition to project: ' . implode('; ', $errors));
        }
    }

    public function removeUser($pid, $uid)
    {
        return $this->client->delete("/gdc/projects/$pid/users/$uid");
    }

    public function leaveProject($pid, $uid)
    {
        return $this->removeUser($pid, $uid);
    }

    public function inviteUser($pid, $email, $role = Projects::ROLE_ADMIN, $filters = [])
    {
        $projectRoleUri = $this->getRoleUri($pid, $role);

        try {
            $result = $this->client->post("/gdc/projects/$pid/invitations", [
                'invitations' => [
                    [
                        'invitation' => [
                            'content' => [
                                'email' => $email,
                                'role' => $projectRoleUri,
                                'userFilters' => $filters
                            ]
                        ]
                    ]
                ]
            ]);
            if (isset($result['createdInvitations']['uri']) && count($result['createdInvitations']['uri'])) {
                return current($result['createdInvitations']['uri']);
            } else {
                if (isset($result['createdInvitations']['loginsAlreadyInProject'])
                    && count($result['createdInvitations']['loginsAlreadyInProject'])) {
                    return true;
                }
                throw Exception::unexpectedResponseError(
                    'Invitation to project failed',
                    'POST',
                    "/gdc/projects/pid/invitations",
                    $result
                );
            }
        } catch (Exception $e) {
            if (isset($e->getData()['error']['message'])
                && strpos($e->getData()['error']['message'], 'is already member') !== false) {
                return true;
            } else {
                throw $e;
            }
        }
    }

    public function disableUser($uid, $pid)
    {
        try {
            $result = $this->client->get("/gdc/projects/$pid/users/$uid");
            $this->client->post("/gdc/projects/$pid/users", [
                'user' => [
                    'content' => [
                        'status' => 'DISABLED',
                        'userRoles' => $result['user']['content']['userRoles']
                    ],
                    'links' => [
                        'self' => $result['user']['links']['self']
                    ]
                ]
            ]);
        } catch (Exception $e) {
            if ($e->getCode() != 404) {
                throw $e;
            }
        }
    }

    public function cancelInvitation($pid, $email)
    {
        $result = $this->client->get("/gdc/projects/$pid/invitations");
        foreach ($result['invitations'] as $r) {
            if (strtolower($r['invitation']['content']['email']) != strtolower($email)) {
                continue;
            }
            try {
                $this->client->delete($r['invitation']['links']['self']);
            } catch (Exception $e) {
                // Ignore
            }
        }
    }
}
