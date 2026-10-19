<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/helper/DebugFlowHelper.php';

require_once __DIR__ . '/../libs/helper/VariablePresentationHelper.php';

use Burki24\SymconModuleHelper\VariablePresentationHelper;

class OpenShutterButtonControl extends IPSModuleStrict
{
    use \Burki24\SymconModuleHelper\DebugFlowHelper;

    use VariablePresentationHelper;

    private const DIRECTION_UP = 0;
    private const DIRECTION_DOWN = 1;
    private const POSITION_MODE_ZERO_OPEN = 0;
    private const POSITION_MODE_HUNDRED_OPEN = 1;
    private const MIN_PRESS_TIME = 100;
    private const MAX_PRESS_TIME = 5000;

    /**
     * Registers properties, attributes, messages and the long-press timer.
     */
    public function Create(): void
    {
        $debugFlowResult = null;
        $debugFlowFailed = false;
        $this->DebugFlowStart(__FUNCTION__, __LINE__);
        try {
            parent::Create();

            $this->RegisterPropertyInteger('ButtonID', 0);
            $this->RegisterPropertyInteger('MoveID', 0);
            $this->RegisterPropertyInteger('PositionID', 0);
            $this->RegisterPropertyInteger('Direction', self::DIRECTION_UP);
            $this->RegisterPropertyInteger('ShortPressTime', 1000);
            $this->RegisterPropertyInteger('PositionMode', self::POSITION_MODE_ZERO_OPEN);

            $this->RegisterAttributeFloat('PressStart', 0.0);
            $this->RegisterAttributeBoolean('ButtonPressed', false);
            $this->RegisterAttributeBoolean('LongPressActive', false);
            $this->RegisterAttributeInteger('ActiveMoveID', 0);
            $this->RegisterAttributeInteger('RegisteredButtonID', 0);
            $this->RegisterAttributeString('RegisteredReferences', '[]');

            $this->RegisterStatusVariables();
            $this->RegisterMessage(0, IPS_KERNELSTARTED);

            $this->RegisterTimer(
                'LongPress',
                0,
                'IPS_RequestAction($_IPS[\'TARGET\'], \'HandleLongPress\', 0);'
            );
        } catch (\Throwable $debugFlowException) {
            $debugFlowFailed = true;
            $this->DebugFlowException(__FUNCTION__, __LINE__, $debugFlowException);
            throw $debugFlowException;
        } finally {
            $this->DebugFlowEnd(__FUNCTION__, __LINE__, $debugFlowResult, $debugFlowFailed, true);
        }
    }

    /**
     * Applies the configuration after Symcon has reached KR_READY.
     */
    public function ApplyChanges(): void
    {
        $debugFlowResult = null;
        $debugFlowFailed = false;
        $this->DebugFlowStart(__FUNCTION__, __LINE__);
        try {
            parent::ApplyChanges();

            if (IPS_GetKernelRunlevel() !== KR_READY) {
                $this->SetTimerInterval('LongPress', 0);
                $this->UnregisterConfiguredButton();
                $this->SetStatus(IS_INACTIVE);
                return;
            }

            $this->Initialize();
        } catch (\Throwable $debugFlowException) {
            $debugFlowFailed = true;
            $this->DebugFlowException(__FUNCTION__, __LINE__, $debugFlowException);
            throw $debugFlowException;
        } finally {
            $this->DebugFlowEnd(__FUNCTION__, __LINE__, $debugFlowResult, $debugFlowFailed, true);
        }
    }

    /**
     * Stops active movement and removes registrations before deletion.
     */
    public function Destroy(): void
    {
        $debugFlowResult = null;
        $debugFlowFailed = false;
        $this->DebugFlowStart(__FUNCTION__, __LINE__);
        try {
            $this->SetTimerInterval('LongPress', 0);

            if (IPS_GetKernelRunlevel() === KR_READY) {
                $this->StopActiveMovement();
            } else {
                $this->WriteAttributeInteger('ActiveMoveID', 0);
            }

            $this->ResetPressState(false);
            $this->UnregisterConfiguredButton();
            $this->ClearReferences();
            $this->UnregisterMessage(0, IPS_KERNELSTARTED);

            parent::Destroy();
        } catch (\Throwable $debugFlowException) {
            $debugFlowFailed = true;
            $this->DebugFlowException(__FUNCTION__, __LINE__, $debugFlowException);
            throw $debugFlowException;
        } finally {
            $this->DebugFlowEnd(__FUNCTION__, __LINE__, $debugFlowResult, $debugFlowFailed, true);
        }
    }

    /**
     * Handles kernel and button messages.
     *
     * @param int          $TimeStamp Unix timestamp supplied by Symcon.
     * @param int          $SenderID  Sender object ID.
     * @param int          $Message   Symcon message ID.
     * @param array<mixed> $Data      Message payload.
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $debugFlowResult = null;
        $debugFlowFailed = false;
        $this->DebugFlowStart(__FUNCTION__, __LINE__, ['Quelle' => $SenderID, 'Nachricht' => $Message]);
        try {
            if ($SenderID === 0 && $Message === IPS_KERNELSTARTED) {
                $this->Initialize();
                return;
            }

            $registeredButtonID = $this->ReadAttributeInteger('RegisteredButtonID');
            if ($Message !== VM_UPDATE || $SenderID <= 0 || $SenderID !== $registeredButtonID) {
                return;
            }

            $this->HandleButton();
        } catch (\Throwable $debugFlowException) {
            $debugFlowFailed = true;
            $this->DebugFlowException(__FUNCTION__, __LINE__, $debugFlowException);
            throw $debugFlowException;
        } finally {
            $this->DebugFlowEnd(__FUNCTION__, __LINE__, $debugFlowResult, $debugFlowFailed, true);
        }
    }

    /**
     * Handles the internal long-press timer action.
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        $debugFlowResult = null;
        $debugFlowFailed = false;
        $this->DebugFlowStart(__FUNCTION__, __LINE__, ['Aktion' => $Ident]);
        try {
            if ($Ident !== 'HandleLongPress') {
                throw new InvalidArgumentException('Invalid Ident: ' . $Ident);
            }

            $this->HandleLongPress();
        } catch (\Throwable $debugFlowException) {
            $debugFlowFailed = true;
            $this->DebugFlowException(__FUNCTION__, __LINE__, $debugFlowException);
            throw $debugFlowException;
        } finally {
            $this->DebugFlowEnd(__FUNCTION__, __LINE__, $debugFlowResult, $debugFlowFailed, true);
        }
    }

    /**
     * Initializes references, message registration and module status.
     */
    private function Initialize(): void
    {
        $debugFlowResult = null;
        $debugFlowFailed = false;
        $this->DebugFlowStart(__FUNCTION__, __LINE__);
        try {
            if (IPS_GetKernelRunlevel() !== KR_READY) {
                return;
            }

            $this->ResetPressState(true);
            $this->UpdateReferences();

            $status = $this->DetermineConfigurationStatus();
            $this->UpdateButtonRegistration($status === IS_ACTIVE);
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, $status === IS_ACTIVE ? 'AKTION' : 'FEHLER', $status === IS_ACTIVE ? 'Tastersteuerung betriebsbereit.' : 'Tastersteuerung wegen ungültiger Konfiguration gesperrt; Statuscode benennt die betroffene Einstellung.', ['Status' => $status]);
            $this->SetStatus($status);
        } catch (\Throwable $debugFlowException) {
            $debugFlowFailed = true;
            $this->DebugFlowException(__FUNCTION__, __LINE__, $debugFlowException);
            throw $debugFlowException;
        } finally {
            $this->DebugFlowEnd(__FUNCTION__, __LINE__, $debugFlowResult, $debugFlowFailed, true);
        }
    }

    /**
     * Evaluates the current button value and handles press/release transitions.
     */
    private function HandleButton(): void
    {
        $debugFlowResult = null;
        $debugFlowFailed = false;
        $this->DebugFlowStart(__FUNCTION__, __LINE__);
        try {
            $status = $this->DetermineConfigurationStatus();
            if ($status !== IS_ACTIVE) {
                $this->ResetPressState(true);
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, $status === IS_ACTIVE ? 'AKTION' : 'FEHLER', $status === IS_ACTIVE ? 'Tastersteuerung betriebsbereit.' : 'Tastersteuerung wegen ungültiger Konfiguration gesperrt; Statuscode benennt die betroffene Einstellung.', ['Status' => $status]);
                $this->SetStatus($status);
                return;
            }

            $buttonID = $this->ReadPropertyInteger('ButtonID');
            $rawValue = $this->DebugFlowReadValue($buttonID, __FUNCTION__, __LINE__);
            $pressed = $this->MapButtonState($rawValue);

            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'EMPFANG', 'Tasterwert wird ausgewertet.', ['Variable' => $buttonID, 'Wert' => $rawValue]);

            if ($pressed === null) {
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'FEHLER', 'Tasterwert ist weder als gedrückt noch als losgelassen erkennbar; keine Bewegung ausgelöst.', ['Variable' => $buttonID, 'Wert' => $rawValue]);
                return;
            }

            if ($pressed) {
                $this->HandleButtonPressed();
                return;
            }

            $this->HandleButtonReleased();
        } catch (\Throwable $debugFlowException) {
            $debugFlowFailed = true;
            $this->DebugFlowException(__FUNCTION__, __LINE__, $debugFlowException);
            throw $debugFlowException;
        } finally {
            $this->DebugFlowEnd(__FUNCTION__, __LINE__, $debugFlowResult, $debugFlowFailed, true);
        }
    }

    /**
     * Starts short/long-press timing for a new press transition.
     */
    private function HandleButtonPressed(): void
    {
        if ($this->ReadAttributeBoolean('ButtonPressed')) {
            return;
        }

        $this->WriteAttributeBoolean('ButtonPressed', true);
        $this->WriteAttributeBoolean('LongPressActive', false);
        $this->WriteAttributeFloat('PressStart', microtime(true));
        $this->SetTimerInterval('LongPress', $this->ReadPropertyInteger('ShortPressTime'));

        $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'AKTION', 'Taster gedrückt; Zeitmessung und Langdruck-Timer gestartet.');
    }

    /**
     * Finishes a press transition and executes the matching shutter action.
     */
    private function HandleButtonReleased(): void
    {
        if (!$this->ReadAttributeBoolean('ButtonPressed')) {
            return;
        }

        $this->SetTimerInterval('LongPress', 0);
        $this->WriteAttributeBoolean('ButtonPressed', false);

        $pressStart = $this->ReadAttributeFloat('PressStart');
        $duration = max(0, (int) round((microtime(true) - $pressStart) * 1000));
        $this->WriteAttributeFloat('PressStart', 0.0);
        $this->DebugFlowWriteValue('last_duration_ms', $duration, __FUNCTION__, __LINE__);

        if ($this->ReadAttributeBoolean('LongPressActive')) {
            $this->WriteAttributeBoolean('LongPressActive', false);
            $this->DebugFlowWriteValue('last_action', 'long_press', __FUNCTION__, __LINE__);
            $this->StopActiveMovement();
            return;
        }

        if ($duration < $this->ReadPropertyInteger('ShortPressTime')) {
            $this->DebugFlowWriteValue('last_action', 'short_press', __FUNCTION__, __LINE__);
            $this->HandleShortPress();
            return;
        }

        // The timer should normally have fired already. Temporarily restore the
        // pressed state so a delayed timer still results in OPEN/CLOSE and STOP.
        $this->WriteAttributeBoolean('ButtonPressed', true);
        $this->HandleLongPress();
        $this->WriteAttributeBoolean('ButtonPressed', false);
        $this->WriteAttributeBoolean('LongPressActive', false);
        $this->DebugFlowWriteValue('last_action', 'long_press', __FUNCTION__, __LINE__);
        $this->StopActiveMovement();
    }

    /**
     * Starts continuous shutter movement after the long-press threshold.
     */
    private function HandleLongPress(): void
    {
        $debugFlowResult = null;
        $debugFlowFailed = false;
        $this->DebugFlowStart(__FUNCTION__, __LINE__);
        try {
            $this->SetTimerInterval('LongPress', 0);

            if (!$this->ReadAttributeBoolean('ButtonPressed') || $this->ReadAttributeBoolean('LongPressActive')) {
                return;
            }

            $status = $this->DetermineConfigurationStatus();
            if ($status !== IS_ACTIVE) {
                $this->ResetPressState(true);
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, $status === IS_ACTIVE ? 'AKTION' : 'FEHLER', $status === IS_ACTIVE ? 'Tastersteuerung betriebsbereit.' : 'Tastersteuerung wegen ungültiger Konfiguration gesperrt; Statuscode benennt die betroffene Einstellung.', ['Status' => $status]);
                $this->SetStatus($status);
                return;
            }

            $this->WriteAttributeBoolean('LongPressActive', true);
            $this->DebugFlowWriteValue('last_action', 'long_press', __FUNCTION__, __LINE__);
            $this->MoveShutter();
        } catch (\Throwable $debugFlowException) {
            $debugFlowFailed = true;
            $this->DebugFlowException(__FUNCTION__, __LINE__, $debugFlowException);
            throw $debugFlowException;
        } finally {
            $this->DebugFlowEnd(__FUNCTION__, __LINE__, $debugFlowResult, $debugFlowFailed, true);
        }
    }

    /**
     * Moves the shutter to the configured end position after a short press.
     */
    private function HandleShortPress(): void
    {
        $debugFlowResult = null;
        $debugFlowFailed = false;
        $this->DebugFlowStart(__FUNCTION__, __LINE__);
        try {
            $positionID = $this->ReadPropertyInteger('PositionID');
            $direction = $this->ReadPropertyInteger('Direction');
            $positionMode = $this->ReadPropertyInteger('PositionMode');

            $openPosition = $positionMode === self::POSITION_MODE_HUNDRED_OPEN ? 100 : 0;
            $closedPosition = $positionMode === self::POSITION_MODE_HUNDRED_OPEN ? 0 : 100;
            $targetPosition = $direction === self::DIRECTION_UP ? $openPosition : $closedPosition;
            $positionVariable = IPS_GetVariable($positionID);
            $targetValue = $positionVariable['VariableType'] === VARIABLETYPE_FLOAT
                ? (float) $targetPosition
                : $targetPosition;

            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'AKTION', 'Kurzer Tastendruck: konfigurierte Endposition wird angefordert.', ['Variable' => $positionID, 'PositionProzent' => $targetPosition]);
            $this->DebugFlowRequestAction($positionID, $targetValue, __FUNCTION__, __LINE__);
        } catch (\Throwable $debugFlowException) {
            $debugFlowFailed = true;
            $this->DebugFlowException(__FUNCTION__, __LINE__, $debugFlowException);
            throw $debugFlowException;
        } finally {
            $this->DebugFlowEnd(__FUNCTION__, __LINE__, $debugFlowResult, $debugFlowFailed, true);
        }
    }

    /**
     * Starts continuous movement in the configured direction.
     */
    private function MoveShutter(): void
    {
        $debugFlowResult = null;
        $debugFlowFailed = false;
        $this->DebugFlowStart(__FUNCTION__, __LINE__);
        try {
            $moveID = $this->ReadPropertyInteger('MoveID');
            $direction = $this->ReadPropertyInteger('Direction');
            $command = $direction === self::DIRECTION_UP ? 'OPEN' : 'CLOSE';

            $this->WriteAttributeInteger('ActiveMoveID', $moveID);
            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'AKTION', 'Langer Tastendruck: kontinuierliche Bewegung wird angefordert.', ['Variable' => $moveID, 'Befehl' => $command]);

            if (!$this->DebugFlowRequestAction($moveID, $command, __FUNCTION__, __LINE__)) {
                $this->WriteAttributeInteger('ActiveMoveID', 0);
                $this->WriteAttributeBoolean('LongPressActive', false);
            }
        } catch (\Throwable $debugFlowException) {
            $debugFlowFailed = true;
            $this->DebugFlowException(__FUNCTION__, __LINE__, $debugFlowException);
            throw $debugFlowException;
        } finally {
            $this->DebugFlowEnd(__FUNCTION__, __LINE__, $debugFlowResult, $debugFlowFailed, true);
        }
    }

    /**
     * Stops the movement on the exact variable that started it.
     */
    private function StopActiveMovement(): void
    {
        $debugFlowResult = null;
        $debugFlowFailed = false;
        $this->DebugFlowStart(__FUNCTION__, __LINE__);
        try {
            $moveID = $this->ReadAttributeInteger('ActiveMoveID');
            if ($moveID <= 0) {
                return;
            }

            // Clear first so repeated cleanup calls never send duplicate STOP commands.
            $this->WriteAttributeInteger('ActiveMoveID', 0);

            if (!IPS_VariableExists($moveID) || !HasAction($moveID)) {
                $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'FEHLER', 'STOP kann nicht gesendet werden: Bewegungsvariable oder ausführbare Aktion fehlt.', ['Variable' => $moveID]);
                return;
            }

            $this->DebugFlowEvent(__FUNCTION__, __LINE__, 'AKTION', 'Bewegung auf der tatsächlich gestarteten Variable wird mit STOP beendet.', ['Variable' => $moveID]);
            $this->DebugFlowRequestAction($moveID, 'STOP', __FUNCTION__, __LINE__);
        } catch (\Throwable $debugFlowException) {
            $debugFlowFailed = true;
            $this->DebugFlowException(__FUNCTION__, __LINE__, $debugFlowException);
            throw $debugFlowException;
        } finally {
            $this->DebugFlowEnd(__FUNCTION__, __LINE__, $debugFlowResult, $debugFlowFailed, true);
        }
    }

    /**
     * Maps common boolean, numeric and textual button states.
     */
    private function MapButtonState(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return match ((float) $value) {
                1.0     => true,
                0.0     => false,
                default => null
            };
        }

        if (!is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            'pressed', 'press', 'down', 'on', 'true', '1'    => true,
            'released', 'release', 'up', 'off', 'false', '0' => false,
            default                                          => null
        };
    }

    /**
     * Creates the read-only status variables with native Symcon presentations.
     */
    private function RegisterStatusVariables(): void
    {
        $this->RegisterVariableInteger(
            'last_duration_ms',
            $this->Translate('Last press duration'),
            $this->IntegerPresentation('ms', 0, 60000),
            10
        );
        $this->RegisterVariableString(
            'last_action',
            $this->Translate('Last action'),
            $this->OptionsPresentation([
                [
                    'Value'   => '',
                    'Caption' => $this->Translate('No action yet'),
                    'Color'   => 0x808080
                ],
                [
                    'Value'   => 'short_press',
                    'Caption' => $this->Translate('Short press'),
                    'Color'   => 0x00AA00
                ],
                [
                    'Value'   => 'long_press',
                    'Caption' => $this->Translate('Long press'),
                    'Color'   => 0x0066CC
                ]
            ]),
            20
        );
    }

    /**
     * Replaces the previous button-message registration with the current one.
     */
    private function UpdateButtonRegistration(bool $configurationValid): void
    {
        $this->UnregisterConfiguredButton();

        if (!$configurationValid) {
            return;
        }

        $buttonID = $this->ReadPropertyInteger('ButtonID');
        $this->RegisterMessage($buttonID, VM_UPDATE);
        $this->WriteAttributeInteger('RegisteredButtonID', $buttonID);
    }

    /**
     * Removes the message registration stored in the instance attribute.
     */
    private function UnregisterConfiguredButton(): void
    {
        $registeredButtonID = $this->ReadAttributeInteger('RegisteredButtonID');
        if ($registeredButtonID > 0) {
            $this->UnregisterMessage($registeredButtonID, VM_UPDATE);
        }

        $this->WriteAttributeInteger('RegisteredButtonID', 0);
    }

    /**
     * Synchronizes Symcon object references with the configured variables.
     */
    private function UpdateReferences(): void
    {
        $configuredReferences = array_values(array_unique([
            $this->ReadPropertyInteger('ButtonID'),
            $this->ReadPropertyInteger('MoveID'),
            $this->ReadPropertyInteger('PositionID')
        ]));
        $configuredReferences = array_values(array_filter(
            $configuredReferences,
            static fn (int $objectID): bool => $objectID > 0 && IPS_VariableExists($objectID)
        ));

        $registeredReferences = $this->ReadRegisteredReferences();

        foreach (array_diff($registeredReferences, $configuredReferences) as $objectID) {
            $this->UnregisterReference($objectID);
        }

        foreach (array_diff($configuredReferences, $registeredReferences) as $objectID) {
            $this->RegisterReference($objectID);
        }

        sort($configuredReferences);
        $this->WriteAttributeString(
            'RegisteredReferences',
            json_encode($configuredReferences, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Removes all object references previously registered by the module.
     */
    private function ClearReferences(): void
    {
        foreach ($this->ReadRegisteredReferences() as $objectID) {
            $this->UnregisterReference($objectID);
        }

        $this->WriteAttributeString('RegisteredReferences', '[]');
    }

    /**
     * Returns the persisted list of registered object references.
     *
     * @return list<int>
     */
    private function ReadRegisteredReferences(): array
    {
        $references = json_decode($this->ReadAttributeString('RegisteredReferences'), true);
        if (!is_array($references)) {
            return [];
        }

        return array_values(array_filter(
            $references,
            static fn (mixed $objectID): bool => is_int($objectID) && $objectID > 0
        ));
    }

    /**
     * Resets timer and transition state and optionally stops active movement.
     */
    private function ResetPressState(bool $stopMovement): void
    {
        $this->SetTimerInterval('LongPress', 0);

        if ($stopMovement) {
            $this->StopActiveMovement();
        }

        $this->WriteAttributeFloat('PressStart', 0.0);
        $this->WriteAttributeBoolean('ButtonPressed', false);
        $this->WriteAttributeBoolean('LongPressActive', false);
    }

    /**
     * Validates all properties and configured variables.
     */
    private function DetermineConfigurationStatus(): int
    {
        $buttonID = $this->ReadPropertyInteger('ButtonID');
        $moveID = $this->ReadPropertyInteger('MoveID');
        $positionID = $this->ReadPropertyInteger('PositionID');

        if ($buttonID <= 0 || $moveID <= 0 || $positionID <= 0) {
            return 209;
        }

        $shortPressTime = $this->ReadPropertyInteger('ShortPressTime');
        if ($shortPressTime < self::MIN_PRESS_TIME || $shortPressTime > self::MAX_PRESS_TIME) {
            return 206;
        }

        if (!in_array($this->ReadPropertyInteger('Direction'), [self::DIRECTION_UP, self::DIRECTION_DOWN], true)) {
            return 207;
        }

        if (!in_array(
            $this->ReadPropertyInteger('PositionMode'),
            [self::POSITION_MODE_ZERO_OPEN, self::POSITION_MODE_HUNDRED_OPEN],
            true
        )) {
            return 208;
        }

        if (!$this->VariableHasType($buttonID, [VARIABLETYPE_BOOLEAN, VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT, VARIABLETYPE_STRING])) {
            return 201;
        }
        if (!$this->VariableHasType($moveID, [VARIABLETYPE_STRING])) {
            return 202;
        }
        if (!$this->VariableHasType($positionID, [VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT])) {
            return 203;
        }
        if (!HasAction($moveID)) {
            return 204;
        }
        if (!HasAction($positionID)) {
            return 205;
        }

        return IS_ACTIVE;
    }

    /**
     * Checks whether a variable exists and has one of the expected types.
     *
     * @param list<int> $allowedTypes
     */
    private function VariableHasType(int $variableID, array $allowedTypes): bool
    {
        if (!IPS_VariableExists($variableID)) {
            return false;
        }

        $variable = IPS_GetVariable($variableID);

        return in_array($variable['VariableType'], $allowedTypes, true);
    }
}
