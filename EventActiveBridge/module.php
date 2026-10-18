<?php

declare(strict_types=1);

class EventActiveBridge extends IPSModuleStrict
{
    private const DEBUG_TEXT = 0;
    private const EVENT_CHANGE_ACTIVE = 10804;
    private const VARIABLE_UPDATE = 10603;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('EventID', 0);
        $this->RegisterPropertyInteger('CommandVariableID', 0);
        $this->RegisterVariableBoolean('EventActive', 'Wochenplan aktiv', [], 10);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Remove previous message registrations so changed object IDs
        // do not leave stale listeners behind.
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        $eventID = $this->ReadPropertyInteger('EventID');
        $commandVariableID = $this->ReadPropertyInteger('CommandVariableID');

        if ($eventID <= 0) {
            $this->SendDebug('Configuration', 'No EventID configured.', self::DEBUG_TEXT);
            return;
        }

        if (!IPS_EventExists($eventID)) {
            $this->SendDebug(
                'Configuration',
                sprintf('Configured EventID %d does not exist.', $eventID),
                self::DEBUG_TEXT
            );
            return;
        }

        // Initial synchronization after configuration changes or a Symcon restart.
        $event = IPS_GetEvent($eventID);
        $active = (bool) $event['EventActive'];
        $this->SetValue('EventActive', $active);

        $this->RegisterMessage($eventID, self::EVENT_CHANGE_ACTIVE);

        if ($commandVariableID > 0) {
            if (!IPS_VariableExists($commandVariableID)) {
                $this->SendDebug(
                    'Configuration',
                    sprintf('Configured CommandVariableID %d does not exist.', $commandVariableID),
                    self::DEBUG_TEXT
                );
                return;
            }

            $variable = IPS_GetVariable($commandVariableID);
            if ($variable['VariableType'] !== VARIABLETYPE_BOOLEAN) {
                $this->SendDebug(
                    'Configuration',
                    sprintf('CommandVariableID %d is not Boolean.', $commandVariableID),
                    self::DEBUG_TEXT
                );
                return;
            }

            $this->RegisterMessage($commandVariableID, self::VARIABLE_UPDATE);
        }

        $this->SendDebug(
            'Configuration',
            sprintf(
                'Listening for EventID %d (EventActive=%s)%s',
                $eventID,
                $active ? 'true' : 'false',
                $commandVariableID > 0
                    ? sprintf(' and Boolean CommandVariableID %d.', $commandVariableID)
                    : '.'
            ),
            self::DEBUG_TEXT
        );
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $eventID = $this->ReadPropertyInteger('EventID');
        $commandVariableID = $this->ReadPropertyInteger('CommandVariableID');

        if ($Message === self::EVENT_CHANGE_ACTIVE && $SenderID === $eventID) {
            if (!IPS_EventExists($SenderID)) {
                $this->SendDebug(
                    'EventActive',
                    sprintf('Sender EventID %d no longer exists.', $SenderID),
                    self::DEBUG_TEXT
                );
                return;
            }

            $event = IPS_GetEvent($SenderID);
            $active = (bool) $event['EventActive'];

            $this->SetValue('EventActive', $active);

            $this->SendDebug(
                'EventActive',
                sprintf(
                    'EventID %d changed: EventActive=%s',
                    $SenderID,
                    $active ? 'true' : 'false'
                ),
                self::DEBUG_TEXT
            );
            return;
        }

        if ($Message === self::VARIABLE_UPDATE && $SenderID === $commandVariableID) {
            if (!IPS_VariableExists($SenderID) || !IPS_EventExists($eventID)) {
                return;
            }

            $requestedState = GetValueBoolean($SenderID);
            $event = IPS_GetEvent($eventID);
            $currentState = (bool) $event['EventActive'];

            $this->SendDebug(
                'Command',
                sprintf(
                    'CommandVariableID %d requested EventActive=%s (current=%s)',
                    $SenderID,
                    $requestedState ? 'true' : 'false',
                    $currentState ? 'true' : 'false'
                ),
                self::DEBUG_TEXT
            );

            // Avoid unnecessary writes and possible feedback loops.
            if ($requestedState !== $currentState) {
                IPS_SetEventActive($eventID, $requestedState);
            }
        }
    }
}
