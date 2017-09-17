<?php

function adminer_object() {
    class AdminerSoftware extends Adminer {
        function login() {
            return true;
        }
    }
    return new AdminerSoftware;
}
require './adminer.class.php';
