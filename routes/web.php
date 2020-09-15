<?php

Route::group(
    ['middleware' => ['web']], function () {
        Route::prefix('aws')->group(
            function () {
                Route::group(
                    ['as' => 'aws.'], function () {
                    }
                );
            }
        );
    }
);
