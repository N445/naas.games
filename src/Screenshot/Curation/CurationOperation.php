<?php

namespace App\Screenshot\Curation;

enum CurationOperation: string
{
    case AddTags = 'add_tags';
    case RemoveTags = 'remove_tags';
    case AddToAlbum = 'add_album';
    case RemoveFromAlbum = 'remove_album';
    case SetGame = 'set_game';
    case Publish = 'publish';
    case Unpublish = 'unpublish';
    case Delete = 'delete';
}
