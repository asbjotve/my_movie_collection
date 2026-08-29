from typing import Any, Optional

from pydantic import BaseModel, Field, model_validator


class ContentExternalSourceUpdatePayload(BaseModel):
    """Payload for å oppdatere en eksisterende content_external_source-rad.

    Minst ett av feltene må sendes med. Raden må allerede finnes (samme
    content_id + source) - dette endepunktet oppretter IKKE nye rader
    (se update_content_external_source() for begrunnelse).
    """

    external_id: Optional[str] = Field(default=None, max_length=20)
    data_json: Optional[dict[str, Any]] = None

    @model_validator(mode="after")
    def _at_least_one_field(self) -> "ContentExternalSourceUpdatePayload":
        if self.external_id is None and self.data_json is None:
            raise ValueError(
                "Minst ett av feltene external_id eller data_json må sendes med."
            )
        return self
