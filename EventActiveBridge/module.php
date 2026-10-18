<?php

declare(strict_types=1);

class EventActiveBridge extends IPSModuleStrict
{
    private const DEBUG_TEXT = 0;
    private const EVENT_CHANGE_ACTIVE = 10804;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('EventID', 0);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Remove previous message registrations so a changed EventID
        // does not leave stale listeners behind.
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        $eventID = $this->ReadPropertyInteger('EventID');

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

        $this->RegisterMessage($eventID, self::EVENT_CHANGE_ACTIVE);
        $this->SendDebug(
            'Configuration',
            sprintf('Listening for EM_CHANGEACTIVE on EventID %d.', $eventID),
            self::DEBUG_TEXT
        );
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message !== self::EVENT_CHANGE_ACTIVE) {
            return;
        }

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

        $this->SendDebug(
            'EventActive',
            sprintf(
                'EventID %d changed: EventActive=%s',
                $SenderID,
                $active ? 'true' : 'false'
            ),
            self::DEBUG_TEXT
        );

        $this->SendDebug(
            'MessageData',
            json_encode($Data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
            self::DEBUG_TEXT
        );
    }
}
