<?php

declare(strict_types=1);

// A second language for the blog, as in "Your First Site".

// #region add-language
lynx()->languages->update('default', ['name' => 'English', 'locale' => 'en']);
lynx()->languages->add('pt', 'Português', locale: 'pt-PT', fallback: 'default');
// #endregion add-language

// #region translate
$blog = lynx()->pages->get('/blog');
$blog->setLanguageName('pt', 'blogue');
$blog->save();

$post = lynx()->pages->get('/blog/hello-world');
$post->setLanguageName('pt', 'ola-mundo');
$post->setLanguageValue('pt', 'title', 'Olá, mundo');
$post->save();
// #endregion translate
