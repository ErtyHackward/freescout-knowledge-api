<?php

// Public read-only endpoints (no auth, returns only published/public content).
// {mailboxId} and {categoryId} are constrained to digits so that path segments
// like /categories/full are free to be matched by the admin routes below.
Route::group(['prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\KnowledgeBaseApiModule\Http\Controllers'], function () {
    Route::get('/api/knowledgebase/{mailboxId}/categories', ['uses' => 'KnowledgeBaseApiController@get', 'laroute' => false])
        ->where('mailboxId', '[0-9]+')
        ->name('knowledgebase.index');
    Route::get('/api/knowledgebase/{mailboxId}/categories/{categoryId}', ['uses' => 'KnowledgeBaseApiController@category', 'laroute' => false])
        ->where(['mailboxId' => '[0-9]+', 'categoryId' => '[0-9]+'])
        ->name('knowledgebase.category');
});

// Authenticated admin endpoints — reuse ApiWebhooks' API key via its ApiAuth middleware.
// Requires the ApiWebhooks module to be installed and a key configured.
if (class_exists(\Modules\ApiWebhooks\Http\Middleware\ApiAuth::class)) {
    Route::group([
        'prefix' => \Helper::getSubdirectory(),
        'namespace' => 'Modules\KnowledgeBaseApiModule\Http\Controllers',
        'middleware' => [\Modules\ApiWebhooks\Http\Middleware\ApiAuth::class],
    ], function () {
        // Categories
        Route::get('/api/knowledgebase/{mailboxId}/categories/full', 'KnowledgeBaseAdminApiController@categoriesFull')->name('knowledgebase.admin.categories_full');
        Route::post('/api/knowledgebase/{mailboxId}/categories', 'KnowledgeBaseAdminApiController@categoryCreate')->name('knowledgebase.admin.category_create');
        Route::put('/api/knowledgebase/{mailboxId}/categories/reorder', 'KnowledgeBaseAdminApiController@categoriesReorder')->name('knowledgebase.admin.categories_reorder');
        Route::get('/api/knowledgebase/categories/{categoryId}', 'KnowledgeBaseAdminApiController@categoryShow')->name('knowledgebase.admin.category_show');
        Route::put('/api/knowledgebase/categories/{categoryId}', 'KnowledgeBaseAdminApiController@categoryUpdate')->name('knowledgebase.admin.category_update');
        Route::delete('/api/knowledgebase/categories/{categoryId}', 'KnowledgeBaseAdminApiController@categoryDelete')->name('knowledgebase.admin.category_delete');

        // Articles
        Route::get('/api/knowledgebase/{mailboxId}/articles', 'KnowledgeBaseAdminApiController@articlesList')->name('knowledgebase.admin.articles_list');
        Route::post('/api/knowledgebase/{mailboxId}/articles', 'KnowledgeBaseAdminApiController@articleCreate')->name('knowledgebase.admin.article_create');
        Route::get('/api/knowledgebase/articles/{articleId}', 'KnowledgeBaseAdminApiController@articleShow')->name('knowledgebase.admin.article_show');
        Route::put('/api/knowledgebase/articles/{articleId}', 'KnowledgeBaseAdminApiController@articleUpdate')->name('knowledgebase.admin.article_update');
        Route::delete('/api/knowledgebase/articles/{articleId}', 'KnowledgeBaseAdminApiController@articleDelete')->name('knowledgebase.admin.article_delete');
        Route::put('/api/knowledgebase/articles/{articleId}/categories', 'KnowledgeBaseAdminApiController@articleSetCategories')->name('knowledgebase.admin.article_set_categories');

        // Attachments
        Route::post('/api/knowledgebase/{mailboxId}/attachments', 'KnowledgeBaseAdminApiController@attachmentUpload')->name('knowledgebase.admin.attachment_upload');
    });
}
