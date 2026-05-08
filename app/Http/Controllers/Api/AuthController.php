<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SessionTracker;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Mail\WelcomeUser;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordCode;
use Carbon\Carbon;

class AuthController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    // POST /api/auth/register
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
        $validated['role']     = 'client';

        // Tracking dès l'inscription
        $validated = array_merge($validated, SessionTracker::collect($request), [
            'last_login_at' => now(),
        ]);

        $user  = User::create($validated);
        Mail::to($user->email)->send(new WelcomeUser($user));
        $token = $user->createToken('brc-market-web')->plainTextToken;

        return response()->json([
            'message' => 'Compte créé avec succès ! Bienvenue sur BRC Market 🎉',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ], 201);
    }


    public function registerAdmin(Request $request): JsonResponse
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
        $validated['role']     = 'admin';

        // Tracking dès l'inscription
        $validated = array_merge($validated, SessionTracker::collect($request), [
            'last_login_at' => now(),
        ]);

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
    // POST /api/admin/users
    // ══════════════════════════════════════════════════════════════════════
    public function createUser(Request $request): JsonResponse
    {
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
            'role'       => ['required', 'in:livreur,user,admin'],
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

        // ── Tracking session ──────────────────────────────────────────────
        $user->update(array_merge(
            SessionTracker::collect($request),
            ['last_login_at' => now()]
        ));

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
    // GET /api/admin/users
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
    // GET /api/admin/users/{id}
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
    // PATCH /api/admin/users/{id}/role
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
    // PATCH /api/admin/orders/{orderId}/assign
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
            'success'            => true,
            'message'            => "Commande assignée à {$livreur->first_name} {$livreur->last_name}.",
            'status'             => 'processing',
            'delivery_driver_id' => $livreur->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // PUT /api/profile
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
    // PUT /api/profile/password
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
    // POST /api/auth/forgot-password
    // ══════════════════════════════════════════════════════════════════════
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Aucun compte associé à cet email.'], 404);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'reset_code'            => Hash::make($code),
            'reset_code_expires_at' => Carbon::now()->addMinutes(15),
        ]);

        Mail::to($user->email)->send(new ResetPasswordCode($code, $user->first_name));

        return response()->json(['message' => 'Code de réinitialisation envoyé par email.']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // POST /api/auth/verify-reset-code
    // ══════════════════════════════════════════════════════════════════════
    public function verifyResetCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code'  => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->reset_code || !$user->reset_code_expires_at) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }
        if (Carbon::now()->isAfter($user->reset_code_expires_at)) {
            return response()->json(['message' => 'Code expiré. Demandez-en un nouveau.'], 422);
        }
        if (!Hash::check($request->code, $user->reset_code)) {
            return response()->json(['message' => 'Code incorrect.'], 422);
        }

        return response()->json(['message' => 'Code vérifié avec succès.']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // POST /api/auth/reset-password
    // ══════════════════════════════════════════════════════════════════════
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'                 => ['required', 'email'],
            'code'                  => ['required', 'string'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->reset_code || !$user->reset_code_expires_at) {
            return response()->json(['message' => 'Demande invalide.'], 422);
        }
        if (Carbon::now()->isAfter($user->reset_code_expires_at)) {
            return response()->json(['message' => 'Code expiré. Recommencez.'], 422);
        }
        if (!Hash::check($request->code, $user->reset_code)) {
            return response()->json(['message' => 'Code incorrect.'], 422);
        }

        $user->update([
            'password'              => Hash::make($request->password),
            'reset_code'            => null,
            'reset_code_expires_at' => null,
        ]);

        $user->tokens()->delete();

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès.']);
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/users/stats
    // ══════════════════════════════════════════════════════════════════════
    public function userStats(): JsonResponse
    {
        return response()->json([
            'total' => User::count(),
            'new'   => User::where('created_at', '>=', now()->subDay())->count(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/users/dashboard-stats
    // ══════════════════════════════════════════════════════════════════════
    public function userDashboardStats(): JsonResponse
    {
        $now       = now();
        $thisMonth = User::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();
        $lastMonth = User::whereMonth('created_at', $now->copy()->subMonth()->month)
                         ->whereYear('created_at',  $now->copy()->subMonth()->year)->count();
        $change    = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100) : 0;

        return response()->json([
            'total'  => User::count(),
            'change' => $change,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/analytics/stats
    // ══════════════════════════════════════════════════════════════════════
    public function analyticsStats(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 7);
        $from = now()->subDays($days - 1)->startOfDay();

        $total       = User::count();
        $onlineNow   = User::where('last_login_at', '>=', now()->subMinutes(5))->count();
        $connections = User::where('last_login_at', '>=', $from)->count();
        $newUsers    = User::where('created_at', '>=', $from)->count();
        $returning   = max(0, $connections - $newUsers);

        return response()->json([
            'total_users'       => $total,
            'online_now'        => $onlineNow,
            'total_connections' => $connections,
            'new_users'         => $newUsers,
            'returning_users'   => $returning,
            'avg_per_day'       => $days > 0 ? round($connections / $days) : 0,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/analytics/daily
    // ══════════════════════════════════════════════════════════════════════
    public function analyticsDaily(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 7);
        $from = now()->subDays($days - 1)->startOfDay();

        $logins = User::selectRaw('DATE(last_login_at) as date, COUNT(*) as connections')
            ->where('last_login_at', '>=', $from)
            ->groupBy('date')
            ->orderBy('date')
            ->get()->keyBy('date');

        $newPerDay = User::selectRaw('DATE(created_at) as date, COUNT(*) as new_users')
            ->where('created_at', '>=', $from)
            ->groupBy('date')
            ->orderBy('date')
            ->get()->keyBy('date');

        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $date   = now()->subDays($days - 1 - $i)->toDateString();
            $conn   = $logins[$date]->connections ?? 0;
            $new    = $newPerDay[$date]->new_users ?? 0;
            $data[] = [
                'day'         => Carbon::parse($date)->locale('fr')->isoFormat('ddd'),
                'date'        => Carbon::parse($date)->format('d/m'),
                'connections' => $conn,
                'newUsers'    => $new,
                'returning'   => max(0, $conn - $new),
            ];
        }

        return response()->json($data);
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/analytics/sessions
    // ══════════════════════════════════════════════════════════════════════
    public function analyticsSessions(): JsonResponse
    {
        $threshold = now()->subMinutes(5);

        $sessions = User::whereNotNull('last_login_at')
            ->orderBy('last_login_at', 'desc')
            ->limit(10)
            ->get(['id','first_name','last_name','email','role',
                   'last_login_at','last_device_type','last_browser',
                   'last_os','last_city']);

        return response()->json($sessions->map(fn($u) => [
            'id'     => $u->id,
            'name'   => $u->first_name . ' ' . $u->last_name,
            'email'  => $u->email,
            'role'   => $u->role,
            'device' => trim(($u->last_browser ?? 'Inconnu') . ' / ' . ($u->last_os ?? '')),
            'location' => $u->last_city ?? 'Inconnue',
            'time'   => $u->last_login_at->diffForHumans(),
            'online' => $u->last_login_at->isAfter($threshold),
        ]));
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/analytics/devices
    // ══════════════════════════════════════════════════════════════════════
    public function analyticsDevices(): JsonResponse
    {
        $total = User::whereNotNull('last_device_type')->count();

        if ($total === 0) {
            return response()->json([]);
        }

        $colorMap = [
            'mobile'  => 'bg-[#274a82]',
            'tablet'  => 'bg-gray-300',
            'desktop' => 'bg-[#e60012]',
        ];
        $labelMap = [
            'mobile'  => 'Mobile',
            'tablet'  => 'Tablette',
            'desktop' => 'Desktop',
        ];

        $rows = User::selectRaw('last_device_type, COUNT(*) as cnt')
            ->whereNotNull('last_device_type')
            ->groupBy('last_device_type')
            ->get();

        return response()->json($rows->map(fn($r) => [
            'label'   => $labelMap[$r->last_device_type] ?? $r->last_device_type,
            'percent' => round(($r->cnt / $total) * 100),
            'color'   => $colorMap[$r->last_device_type] ?? 'bg-gray-400',
        ]));
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/analytics/cities
    // ══════════════════════════════════════════════════════════════════════
    public function analyticsCities(): JsonResponse
    {
        $total = User::whereNotNull('last_city')->count();

        if ($total === 0) {
            return response()->json([]);
        }

        $rows = User::selectRaw('last_city, COUNT(*) as cnt')
            ->whereNotNull('last_city')
            ->groupBy('last_city')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get();

        $max = $rows->first()->cnt ?? 1;

        return response()->json($rows->map(fn($r) => [
            'name'    => $r->last_city,
            'count'   => $r->cnt,
            'percent' => round(($r->cnt / $max) * 100),
        ]));
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/analytics/hourly
    // ══════════════════════════════════════════════════════════════════════
    public function analyticsHourly(): JsonResponse
    {
        $from = now()->subDays(30);

        $rows = User::selectRaw('HOUR(last_login_at) as hour, COUNT(*) as cnt')
            ->where('last_login_at', '>=', $from)
            ->groupBy('hour')
            ->pluck('cnt', 'hour');

        // Tableau de 0 à 23
        $result = array_map(fn($h) => (int) ($rows[$h] ?? 0), range(0, 23));

        return response()->json($result);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Private — formatage uniforme
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