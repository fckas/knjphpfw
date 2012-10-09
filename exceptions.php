<?php

class NotFoundExc extends exception
{
}

function thrownew($msg, $exc = 'Exception')
{
    throw new $exc($msg);
}
