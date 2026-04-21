<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Mail\WelcomeUser; // N'oublie pas l'import !
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    // POST /api/auth/register  — Inscription publique (rôle = client)
    // ══════════════════════════════════════════════════════════════════════
    public function register(Request $request): JsonResponse
    {
        $username = Str::slug($request->first_name . '.' . $request->last_name);
        $request->merge(['username' => $username]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'username'   => ['required', 'string', 'max:50', 'unique:users,username', 'alpha_dash'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'password'   => ['required', 'string', 'min:8'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['role']     = 'client'; // toujours client à l'inscription publique

        $user  = User::create($validated);
        Mail::to($user->email)->send(new WelcomeUser($user));
        $token = $user->createToken('brc-market-web')->plainTextToken;

        return response()->json([
            'message' => 'Compte créé avec succès ! Bienvenue sur BRC Market 🎉',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ], 201);
    }

    // ══════════════════════════════════════════════════════════════════════
    // POST /api/admin/users  — Admin crée un utilisateur avec rôle libre
    // Même logique que register, seule différence : le rôle est imposé
    // ══════════════════════════════════════════════════════════════════════
    public function createUser(Request $request): JsonResponse
    {
        // Username auto depuis prénom+nom, évite les doublons
        $base     = Str::slug($request->first_name . '.' . $request->last_name);
        $username = $base;
        $i        = 1;
        while (User::where('username', $username)->exists()) {
            $username = $base . '-' . $i++;
        }
        $request->merge(['username' => $username]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'username'   => ['required', 'string', 'max:50', 'unique:users,username', 'alpha_dash'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'password'   => ['required', 'string', 'min:8'],
            'role'       => ['required', 'in:livreur,user,admin'], // l'admin crée livreur, user ou admin
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json([
            'message' => "Compte {$validated['role']} créé avec succès.",
            'user'    => array_merge($this->formatUser($user), [
                'is_blocked'   => false,
                'orders_count' => 0,
                'created_at'   => $user->created_at,
            ]),
        ], 201);
    }

    // ══════════════════════════════════════════════════════════════════════
    // POST /api/auth/login
    // ══════════════════════════════════════════════════════════════════════
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Identifiants incorrects.'], 401);
        }

        if ($user->is_blocked) {
            return response()->json([
                'message' => 'Compte suspendu : ' . ($user->blocked_reason ?? 'Contactez le support.'),
            ], 403);
        }

        $user->update(['last_login_at' => now()]);
        $user->tokens()->delete();
        $token = $user->createToken('brc-market-web')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie !',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/auth/me
    // ══════════════════════════════════════════════════════════════════════
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    // ══════════════════════════════════════════════════════════════════════
    // POST /api/auth/logout
    // ══════════════════════════════════════════════════════════════════════
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnecté avec succès.']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/users  — Liste tous les utilisateurs
    // ══════════════════════════════════════════════════════════════════════
    public function listUsers(Request $request): JsonResponse
    {
        $query = User::withCount('orders')->orderBy('created_at', 'desc');

        if ($request->filled('role'))    $query->where('role', $request->role);
        if ($request->filled('blocked')) $query->where('is_blocked', filter_var($request->blocked, FILTER_VALIDATE_BOOLEAN));
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name',  'like', "%{$s}%")
                ->orWhere('email',      'like', "%{$s}%")
            );
        }

        return response()->json($query->paginate($request->per_page ?? 100));
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/users/{id}  — Détail d'un utilisateur
    // ══════════════════════════════════════════════════════════════════════
    public function showUser(int $id): JsonResponse
    {
        $user = User::withCount('orders')
            ->with(['orders' => fn($q) => $q->latest()->limit(5)->select('id','order_number','total','status','created_at')])
            ->findOrFail($id);

        return response()->json($user);
    }

    // ══════════════════════════════════════════════════════════════════════
    // PATCH /api/admin/users/{id}/block
    // ══════════════════════════════════════════════════════════════════════
    public function blockUser(int $id): JsonResponse
    {
        User::findOrFail($id)->update(['is_blocked' => true]);
        return response()->json(['success' => true, 'message' => 'Utilisateur bloqué.']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // PATCH /api/admin/users/{id}/unblock
    // ══════════════════════════════════════════════════════════════════════
    public function unblockUser(int $id): JsonResponse
    {
        User::findOrFail($id)->update(['is_blocked' => false]);
        return response()->json(['success' => true, 'message' => 'Utilisateur débloqué.']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // PATCH /api/admin/users/{id}/role  — Changer le rôle
    // ══════════════════════════════════════════════════════════════════════
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'role' => 'required|in:client,livreur,user,admin',
        ]);

        $user = User::findOrFail($id);

        if ($user->role === 'super_admin') {
            return response()->json(['message' => 'Impossible de modifier le rôle d\'un super administrateur.'], 403);
        }

        $user->update(['role' => $data['role']]);

        return response()->json(['success' => true, 'message' => "Rôle mis à jour : {$data['role']}.", 'user' => $user]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // PATCH /api/admin/orders/{orderId}/assign  — Assigner un livreur
    // ══════════════════════════════════════════════════════════════════════
    public function assignDelivery(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'delivery_driver_id' => 'required|exists:users,id',
        ]);

        $livreur = User::findOrFail($data['delivery_driver_id']);
        if ($livreur->role !== 'livreur') {
            return response()->json(['message' => 'Cet utilisateur n\'est pas un livreur.'], 422);
        }

        $order = \App\Models\Order::findOrFail($orderId);
        $order->update([
            'delivery_driver_id' => $livreur->id,
            'status'             => 'processing',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Commande assignée à {$livreur->first_name} {$livreur->last_name}.",
            'status'  => 'processing',
            'delivery_driver_id' => $livreur->id,
        ]);
    }


    // ══════════════════════════════════════════════════════════════════════
    
    // PUT /api/profile  — Mettre à jour le profil
    // ══════════════════════════════════════════════════════════════════════
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone'      => ['nullable', 'string', 'max:20'],
            'birthdate'  => ['nullable', 'date'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profil mis à jour avec succès.',
            'user'    => $this->formatUser($user),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // PUT /api/profile/password  — Changer le mot de passe
    // ══════════════════════════════════════════════════════════════════════
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password'      => ['required', 'string'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Mot de passe actuel incorrect.',
                'errors'  => ['current_password' => ['Le mot de passe actuel est incorrect.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Mot de passe mis à jour avec succès.']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Formatage utilisateur uniforme (évite la répétition)
    // ══════════════════════════════════════════════════════════════════════
    private function formatUser(User $user): array
    {
        return [
            'id'         => $user->id,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'username'   => $user->username,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'role'       => $user->role,
            'avatar'     => $user->avatar ?? null,
        ];
    }
}