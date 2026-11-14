<?php

/* Container-only configuration: never print private values or override invocation-specific test settings. */
$runtimeSecrets = '/run/secrets/application';
if (is_file($runtimeSecrets)) {
    try {
        $runtimeValues = json_decode(file_get_contents($runtimeSecrets), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($runtimeValues)) {
            throw new RuntimeException();
        }
        foreach ($runtimeValues as $runtimeName => $runtimeValue) {
            if (!is_string($runtimeName) || !preg_match('/^[A-Z][A-Z0-9_]*$/D', $runtimeName) || !is_string($runtimeValue)) {
                throw new RuntimeException();
            }
            if (getenv($runtimeName) === false || getenv($runtimeName) === '') {
                putenv($runtimeName.'='.$runtimeValue);
            }
        }
    } catch (Throwable) {
        throw new RuntimeException('Invalid installation secret configuration.');
    }
}
unset($runtimeSecrets, $runtimeValues, $runtimeName, $runtimeValue);
