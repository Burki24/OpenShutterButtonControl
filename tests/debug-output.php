<?php

declare(strict_types=1);

require_once __DIR__ . '/module.php';

class DebugShutterHarness extends OpenShutterButtonControl
{
    public bool $failDebug = false;

    public function SendDebug(string $message, mixed $data, int $format): void
    {
        if ($this->failDebug) {
            throw new RuntimeException('Debug transport unavailable.');
        }
        parent::SendDebug($message, $data, $format);
    }
}

$debugShutter = new DebugShutterHarness();
$debugShutter->InstanceID = 7777;
$debugShutter->Create();
configureModule($debugShutter);
$debugShutter->ApplyChanges();
$debugShutter->debug = [];
$GLOBALS['OSBC_REQUEST_ACTIONS'] = [];
$GLOBALS['OSBC_VARIABLES'][10]['value'] = true;
$debugShutter->MessageSink(time(), 10, VM_UPDATE, [true]);
$debugShutter->RequestAction('HandleLongPress', 0);
$GLOBALS['OSBC_VARIABLES'][10]['value'] = false;
$debugShutter->MessageSink(time(), 10, VM_UPDATE, [false]);
assertSameValue([['id' => 20, 'value' => 'OPEN'], ['id' => 20, 'value' => 'STOP']], $GLOBALS['OSBC_REQUEST_ACTIONS'], 'Tracing must preserve exactly one movement and its STOP on the original target.');
$stack = [];
$text = '';
foreach ($debugShutter->debug as $entry) {
    $data = json_decode($entry['data'], true, 512, JSON_THROW_ON_ERROR);
    assertSameValue(7777, $data['Instanz'], 'Debug instance context is missing.');
    assertTrue($data['Vorgang'] !== '', 'Each traced entry needs an operation.');
    if (str_contains($entry['message'], ' | START | ')) {
        $stack[] = [$data['Vorgang'], $data['Aufruf']];
    } elseif (str_contains($entry['message'], ' | ENDE | ')) {
        assertSameValue(array_pop($stack), [$data['Vorgang'], $data['Aufruf']], 'Debug starts/ends must balance.');
        assertTrue(isset($data['Bezug']['Ergebnis']), 'Function end must contain a result.');
    }
    $text .= $entry['data'];
}
assertSameValue([], $stack, 'Trace must close after release.');
assertTrue(str_contains($text, 'Steuerwert') && str_contains($text, 'STOP') && str_contains($text, 'Tasterwert'), 'Commands, STOP and received values need German explanations.');
$debugShutter->failDebug = true;
$GLOBALS['OSBC_VARIABLES'][10]['value'] = true;
$debugShutter->MessageSink(time(), 10, VM_UPDATE, [true]);
$debugShutter->RequestAction('HandleLongPress', 0);
$GLOBALS['OSBC_VARIABLES'][10]['value'] = false;
$debugShutter->MessageSink(time(), 10, VM_UPDATE, [false]);
assertSameValue(['id' => 20, 'value' => 'STOP'], end($GLOBALS['OSBC_REQUEST_ACTIONS']), 'An unavailable debug channel must never prevent STOP.');
echo "Shutter debug-output checks passed.\n";
