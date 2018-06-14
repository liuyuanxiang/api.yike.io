<?php
namespace App\Http\Controllers;

use App\Http\Resources\ActivityResource;
use App\Http\Resources\NotificationResource;
use App\Notification;
use App\Notifications\NewFollower;
use App\Notifications\Welcome;
use UrlSigner;
use App\User;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['index', 'show', 'activate', 'followers', 'followings', 'updateEmail']);
    }

    public function index(Request $request)
    {
        $users = User::filter($request->all())->paginate($request->get('per_page', 20));

        return UserResource::collection($users);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function notifications(Request $request)
    {
        $notifications = Notification::whereNotifiableId(auth()->id())
            ->latest()
            ->filter($request->all())
            ->paginate($request->get('per_page', 20));

        return NotificationResource::collection($notifications);
    }

    public function follow(User $user)
    {
        auth()->user()->follow($user);

        $user->notify(new NewFollower(auth()->user()));

        return response()->json([]);
    }

    public function unfollow(User $user)
    {
        auth()->user()->unfollow($user);

        return response()->json([]);
    }

    public function followers(Request $request, User $user)
    {
        $users = $user->followers()->paginate($request->get('per_page', 20));

        return UserResource::collection($users);
    }

    public function followings(Request $request, User $user)
    {
        $users = $user->followings()->paginate($request->get('per_page', 20));

        return UserResource::collection($users);
    }

    public function activities(Request $request, User $user)
    {
        $activities = $user->activities()->paginate($request->get('per_page', 20));

        return ActivityResource::collection($activities);
    }

    public function sendActiveMail(Request $request)
    {
        $request->user()->sendActiveMail();

        return response()->json([
            'message' => '激活邮件已发送，请注意查收！'
        ]);
    }

    public function activate(Request $request)
    {
        if (UrlSigner::validate($request->fullUrl())) {
            User::whereEmail($request->email)->first()->activate();

            auth()->user()->notify(new Welcome());

            return redirect(config('app.site_url').'?active-success=yes&type=register');
        }

        return redirect(config('app.site_url').'?active-success=no&type=register');
    }

    public function editEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users'
        ]);

        $request->user()->sendUpdateMail($request->get('email'));

        return response()->json([
            'message' => '确认邮件已发送到新邮箱，请注意查收！'
        ]);
    }

    public function updateEmail(Request $request)
    {
        if (UrlSigner::validate($request->fullUrl())) {
            $user = User::findOrFail($request->get('user_id'));

            $user->update(['email' => $request->get('email')]);

            return redirect(config('app.site_url') . '?active-success=yes&type=email');
        }

        return redirect(config('app.site_url').'?active-success=no&type=email');
    }

    /**
     * Display the specified resource.
     *
     * @param    \App\User   $user
     *
     * @return  \App\Http\Resources\UserResource
     */
    public function show(User $user)
    {
        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param    \Illuminate\Http\Request  $request
     * @param    \App\User   $user
     *
     * @return  \App\Http\Resources\UserResource
     *
     * @throws  \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', auth()->user(), $user);

        $this->validate($request, [
            // validation rules...
        ]);

        $user->update($request->only([
            'avatar', 'realname', 'bio', 'extends', 'settings', 'cache', 'gender'
        ]));

        return new UserResource($user);
    }
}
