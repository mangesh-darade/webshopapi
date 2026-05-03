<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Backward-compatible alias controller.
 * Keeps existing reciept/* URLs working while allowing receipt/* usage.
 */
class Receipt extends Reciept
{
}
