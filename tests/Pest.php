<?php

use App\enum\DocumentType;
use App\Models\Stop;
use App\Models\TravelRoute;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Give the user their whole required document set, all awaiting review.
 *
 * Shared by the badge tests and the admin panel tests, which approach the
 * same review from the service and from the panel respectively.
 *
 * @return array<string, UserDocument>
 */
function submitRequiredDocuments(User $user): array
{
    $documents = [];

    foreach (DocumentType::requiredFor($user->role) as $type) {
        $documents[$type->name] = UserDocument::factory()
            ->for($user)
            ->ofType($type)
            ->create();
    }

    app(VerificationService::class)->refreshBadge($user);

    return $documents;
}

/**
 * One town's position on one corridor, read straight off the pivot.
 *
 * Shared by the two panel tests that lay out a road - from the corridor's end
 * and from the town's - because the sequence is what both are really about.
 */
function sequenceOn(TravelRoute $route, Stop $stop): int
{
    return (int) $route->stops()
        ->whereKey($stop->getKey())
        ->sole()
        ->getAttribute('pivot')
        ->getAttribute('sequence');
}
