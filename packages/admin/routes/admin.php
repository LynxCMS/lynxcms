<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LynxCms\Admin\Http\AdminLocaleController;
use LynxCms\Admin\Http\CreatePageController;
use LynxCms\Admin\Http\EditFieldController;
use LynxCms\Admin\Http\EditLanguageController;
use LynxCms\Admin\Http\EditPageController;
use LynxCms\Admin\Http\EditRoleController;
use LynxCms\Admin\Http\EditTemplateController;
use LynxCms\Admin\Http\SuperuserScreenController;

// The admin's own address opens the page tree; guests sign in first, as for every screen.
Route::get('/', fn () => redirect()->route('lynx.admin.pages'))->name('home');
Route::view('pages', 'lynx-admin::pages.index')->name('pages');
Route::get('pages/create', CreatePageController::class)->name('pages.create');
Route::get('pages/{page}/edit', EditPageController::class)->whereNumber('page')->name('pages.edit');
Route::view('fields', 'lynx-admin::fields.index')->name('fields');
Route::get('fields/create', EditFieldController::class)->name('fields.create');
Route::get('fields/{field}/edit', EditFieldController::class)->where('field', '[a-z][a-z0-9_]*')->name('fields.edit');
Route::view('templates', 'lynx-admin::templates.index')->name('templates');
Route::get('templates/create', EditTemplateController::class)->name('templates.create');
Route::get('templates/{template}/edit', EditTemplateController::class)->where('template', '[a-z][a-z0-9_-]*')->name('templates.edit');
Route::view('site-texts', 'lynx-admin::site-texts')->name('site-texts');
Route::get('languages', [EditLanguageController::class, 'index'])->name('languages');
Route::get('languages/create', [EditLanguageController::class, 'edit'])->name('languages.create');
Route::get('languages/{language}/edit', [EditLanguageController::class, 'edit'])->where('language', '[a-z0-9][a-z0-9_-]*')->name('languages.edit');
Route::get('roles', [SuperuserScreenController::class, 'roles'])->name('roles');
Route::get('roles/create', EditRoleController::class)->name('roles.create');
Route::get('roles/{role}/edit', EditRoleController::class)->where('role', '[a-z][a-z0-9_-]*')->name('roles.edit');
Route::get('users', [SuperuserScreenController::class, 'users'])->name('users');
Route::get('general', [SuperuserScreenController::class, 'general'])->name('general');
Route::post('locale', AdminLocaleController::class)->name('locale');
