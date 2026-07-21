<?php

namespace App\Enum;

/**
 * The fixed set of categories the Chuck Norris API supports (see /jokes/categories).
 */
enum JokeCategory: string
{
    case Animal = 'animal';
    case Career = 'career';
    case Celebrity = 'celebrity';
    case Dev = 'dev';
    case Explicit = 'explicit';
    case Fashion = 'fashion';
    case Food = 'food';
    case History = 'history';
    case Money = 'money';
    case Movie = 'movie';
    case Music = 'music';
    case Political = 'political';
    case Religion = 'religion';
    case Science = 'science';
    case Sport = 'sport';
    case Travel = 'travel';
}
