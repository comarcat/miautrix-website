<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\Project;
use App\Models\SocialProfileGroup;
use App\Models\Theme;
use App\Models\Tool;
use App\Policies\ArticlePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\ProfilePolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SocialProfileGroupPolicy;
use App\Policies\ThemePolicy;
use App\Policies\ToolPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Profile::class => ProfilePolicy::class,
        Project::class => ProjectPolicy::class,
        Article::class => ArticlePolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        SocialProfileGroup::class => SocialProfileGroupPolicy::class,
        Theme::class => ThemePolicy::class,
        Tool::class => ToolPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
