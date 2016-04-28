# SyncScript: MySQL to Laravel SyncScript Generator

[![Latest Stable Version](https://poser.pugx.org/awkwardideas/syncscript/v/stable)](https://packagist.org/packages/awkwardideas/syncscript)
[![Total Downloads](https://poser.pugx.org/awkwardideas/syncscript/downloads)](https://packagist.org/packages/awkwardideas/syncscript)
[![Latest Unstable Version](https://poser.pugx.org/awkwardideas/syncscript/v/unstable)](https://packagist.org/packages/awkwardideas/syncscript)
[![License](https://poser.pugx.org/awkwardideas/syncscript/license)](https://packagist.org/packages/awkwardideas/syncscript)

## Install Via Composer

composer require awkwardideas/syncscript

## Commands via Artisan

Command line actions are done via artisan.  The host, username, password from the .env file are used for making the connection.

### php artisan syncscript:generate

--from=  Database providing data
--to=  Database receiving data