<?php

declare(strict_types=1);

class EventActiveBridge extends IPSModuleStrict
{
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
            $this->SendDebug('Configuration', 'No EventID configured.', DEBUG_FORMAT_TEXT);
            return;
        }

        if (!IPS_EventExists($eventID)) {
            $this->SendDebug(
                'Configuration',
                sprintf('Configured EventID %d does not exist.', $eventID),
                DEBUG_FORMAT_TEXT
            );
            return;
        }

        $this->RegisterMessage($eventID, EM_CHANGEACTIVE);
        $this->SendDebug(
            'Configuration',
            sprintf('Listening for EM_CHANGEACTIVE on EventID %d.', $eventID),
            DEBUG_FORMAT_TEXT
        );
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message !== EM_CHANGEACTIVE) {
            return;
        }

        if (!IPS_EventExists($SenderID)) {
            $this->SendDebug(
                'EventActive',
                sprintf('Sender EventID %d no longer exists.', $SenderID),
                DEBUG_FORMAT_TEXT
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
            DEBUG_FORMAT_TEXT
        );

        $this->SendDebug(
            'MessageData',
            json_encode($Data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
            DEBUG_FORMAT_TEXT
        );
    }
}
