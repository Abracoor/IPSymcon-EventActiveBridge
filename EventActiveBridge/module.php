<?php

declare(strict_types=1);

class EventActiveBridge extends IPSModuleStrict
{
    private const DEBUG_TEXT = 0;
    private const EVENT_CHANGE_ACTIVE = 10804;
    private const VARIABLE_UPDATE = 10603;
    private const VARIABLE_TYPE_BOOLEAN = 0;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('EventID', 0);
        $this->RegisterPropertyInteger('SourceVariableID', 0);
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
        $sourceVariableID = $this->ReadPropertyInteger('SourceVariableID');

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

        if ($sourceVariableID > 0) {
            if (!IPS_VariableExists($sourceVariableID)) {
                $this->SendDebug(
                    'Configuration',
                    sprintf('Configured SourceVariableID %d does not exist.', $sourceVariableID),
                    self::DEBUG_TEXT
                );
            } else {
                $variable = IPS_GetVariable($sourceVariableID);

                if ((int) $variable['VariableType'] !== self::VARIABLE_TYPE_BOOLEAN) {
                    $this->SendDebug(
                        'Configuration',
                        sprintf('SourceVariableID %d is not a Boolean variable.', $sourceVariableID),
                        self::DEBUG_TEXT
                    );
                } else {
                    $this->RegisterMessage($sourceVariableID, self::VARIABLE_UPDATE);
                    $this->SendDebug(
                        'Configuration',
                        sprintf('Listening for VM_UPDATE on SourceVariableID %d.', $sourceVariableID),
                        self::DEBUG_TEXT
                    );
                }
            }
        }

        $this->SendDebug(
            'Configuration',
            sprintf(
                'Listening for EM_CHANGEACTIVE on EventID %d. Initial EventActive=%s',
                $eventID,
                $active ? 'true' : 'false'
            ),
            self::DEBUG_TEXT
        );
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === self::EVENT_CHANGE_ACTIVE) {
            $this->HandleEventActiveChange($SenderID, $Data);
            return;
        }

        if ($Message === self::VARIABLE_UPDATE) {
            $this->HandleSourceVariableUpdate($SenderID, $Data);
        }
    }

    private function HandleEventActiveChange(int $senderID, array $data): void
    {
        $configuredEventID = $this->ReadPropertyInteger('EventID');

        if ($senderID !== $configuredEventID) {
            return;
        }

        if (!IPS_EventExists($senderID)) {
            $this->SendDebug(
                'EventActive',
                sprintf('Sender EventID %d no longer exists.', $senderID),
                self::DEBUG_TEXT
            );
            return;
        }

        $event = IPS_GetEvent($senderID);
        $active = (bool) $event['EventActive'];

        $this->SetValue('EventActive', $active);

        $this->SendDebug(
            'EventActive',
            sprintf(
                'EventID %d changed: EventActive=%s',
                $senderID,
                $active ? 'true' : 'false'
            ),
            self::DEBUG_TEXT
        );

        $this->SendDebug(
            'MessageData',
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
            self::DEBUG_TEXT
        );
    }

    private function HandleSourceVariableUpdate(int $senderID, array $data): void
    {
        $sourceVariableID = $this->ReadPropertyInteger('SourceVariableID');

        if ($senderID !== $sourceVariableID) {
            return;
        }

        if (!IPS_VariableExists($senderID)) {
            $this->SendDebug(
                'SourceVariable',
                sprintf('Sender VariableID %d no longer exists.', $senderID),
                self::DEBUG_TEXT
            );
            return;
        }

        $eventID = $this->ReadPropertyInteger('EventID');

        if (!IPS_EventExists($eventID)) {
            $this->SendDebug(
                'SourceVariable',
                sprintf('Configured EventID %d does not exist.', $eventID),
                self::DEBUG_TEXT
            );
            return;
        }

        $requestedActive = GetValueBoolean($senderID);
        IPS_SetEventActive($eventID, $requestedActive);

        $this->SendDebug(
            'SourceVariable',
            sprintf(
                'VariableID %d updated: requested EventID %d EventActive=%s',
                $senderID,
                $eventID,
                $requestedActive ? 'true' : 'false'
            ),
            self::DEBUG_TEXT
        );

        $this->SendDebug(
            'SourceMessageData',
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
            self::DEBUG_TEXT
        );
    }
}
