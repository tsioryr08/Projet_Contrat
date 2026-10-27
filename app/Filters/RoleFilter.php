<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Usage : ['filter' => 'role:admin,direction']
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $role = session()->get('role');

        if (! session()->get('utilisateur_id')) {
            return redirect()->to('/connexion');
        }

        if (empty($arguments) || ! in_array($role, $arguments, true)) {
            return service('response')
                ->setStatusCode(403)
                ->setBody(view('errors/html/acces_refuse'));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}