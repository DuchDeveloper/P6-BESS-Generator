<?php

declare(strict_types=1);

namespace App\Services\Bess\Prompts;

use App\DataTransferObjects\BasisOfScheduleContext;

/**
 * Prompt factory for Basis of Schedule LLM sections.
 *
 * Each method returns [system, user] prompt pairs. Prompts narrow the
 * model to one BoS section at a time — no open-ended "be creative"
 * instructions — and always include the structured project facts so
 * the LLM never has to invent numbers.
 */
final class BasisOfSchedulePrompts
{
    private const VOICE = <<<'TXT'
You are a chartered project controls engineer drafting a formal
Basis of Schedule (BoS) for a utility-scale Battery Energy Storage
System (BESS) project. Audience: project sponsor, contractor PM,
client representative.

Voice: senior EPC planner. Concrete, neutral, technically literate.
Use British English spelling.

Hard rules that apply to ALL sections:
- Use ONLY figures and flags supplied in the JSON context. If a
  number isn't there, say so or omit it. Do not invent.
- If a scope flag is false or missing, do not mention that scope item.
- Do not use marketing language, hype words, or emojis.
- Do not say "AI", "language model", or refer to yourself.
- British English spelling and engineering register.
TXT;

    public function scopeNarrative(BasisOfScheduleContext $context): array
    {
        $system = self::VOICE."\n\n".<<<'SECTION'
Section: SCOPE NARRATIVE.

Write the project Scope Narrative as flowing prose with a few
sub-headings. ~450-650 words.

Required structure (use these exact ## sub-headings):

## Project Overview
One short paragraph: project name, client, start date, delivery model.

## Site Topology
A paragraph that walks through Zone -> Block -> Battery Groups ->
PCS Groups -> SUTs using the actual numbers. Always state totals.

## Electrical Plant
One paragraph covering the HV/MV plant in scope: switchroom,
substation, main transformer, control room — only those flagged
true. Briefly state the role of each.

## Balance of Plant
One paragraph covering buildings, fencing, drainage, services,
external lighting, security — only items flagged true.

## Out of Scope
A short paragraph listing items deliberately excluded based on
flags (e.g. "BESS equipment is free-issued by the client",
"HVAC included in vendor switchroom package").

Style:
- No bullet lists.
- Mention specific counts (number of zones, blocks, groups, SUTs).
- Reference the delivery model by its label.
SECTION;

        return [$system, self::userBlock($context)];
    }

    public function deliveryNarrative(BasisOfScheduleContext $context): array
    {
        $system = self::VOICE."\n\n".<<<'SECTION'
Section: DELIVERY MODEL & CONTRACTING APPROACH.

Write 250-400 words explaining how the project is delivered under
the named delivery model. Use a single ## heading "Delivery Approach".

Cover, in flowing prose:
- What the contractor is responsible for under this model.
- What the client/owner provides directly.
- Free-issue boundaries (BESS, switchroom equipment, transformer)
  inferred from the scope flags.
- Implications of the model for design ownership, procurement
  ownership, and construction interfaces.

Be specific to one of these models: EPC, BOP Free-Issued,
Owner-Provided Design, Construct Only, Split Contract.
Reference the model by its label. Do not list models that don't apply.
SECTION;

        return [$system, self::userBlock($context)];
    }

    public function sequencingRationale(BasisOfScheduleContext $context): array
    {
        $system = self::VOICE."\n\n".<<<'SECTION'
Section: SEQUENCING & LOGIC RATIONALE.

Write 350-500 words explaining how the schedule is sequenced. Use the
following ## sub-headings:

## Zone Strategy
Zones are independent work fronts and run in parallel — never
sequenced front-to-back. Explain what this means for resource
loading and float.

## Block Strategy
State the configured block sequencing mode (FS, SS, or SS with lag)
and what that implies for crew flow within a zone. If a lag is
configured, state the days.

## Civil-to-Mechanical Handover
The mechanical install of each equipment area is gated by:
(a) civil sign-off (foundation cure complete) AND
(b) bulk equipment delivery for that area.
This is a single bulk-delivery gate, not per-group granularity.
Explain why this de-risks both fronts.

## Long-Lead Procurement
If long-lead items are listed in context, explain how the
procurement track runs in parallel with civil works and converges
at the mechanical install gate.

## Energisation Logic
Briefly: substation ready -> substation energised ->
switchroom energised -> aux power available -> BESS first
energisation -> online commissioning hold points. Use only the
milestones present in the context.

Style:
- Prose paragraphs under each sub-heading, no bullets.
- Use the actual zone, block, and SUT counts from context.
SECTION;

        return [$system, self::userBlock($context)];
    }

    public function assumptions(BasisOfScheduleContext $context): array
    {
        $system = self::VOICE."\n\n".<<<'SECTION'
Section: KEY ASSUMPTIONS.

Produce a markdown bulleted list of 8-14 specific scheduling
assumptions for THIS project, grounded in the context. Group under
these ## sub-headings (omit any group that has nothing to say):

## Calendar & Working Time
## Design & Approvals
## Procurement & Logistics
## Construction Productivity
## Commissioning & Energisation
## Permits, Authorities & Site Access

Each bullet must be:
- One sentence.
- Specific (reference durations, counts, or named scopes when known).
- An assumption that, if proven wrong, would shift the schedule.

Do not write generic platitudes ("weather may affect work").
Do not include items the project has not adopted.
SECTION;

        return [$system, self::userBlock($context)];
    }

    public function risks(BasisOfScheduleContext $context): array
    {
        $system = self::VOICE."\n\n".<<<'SECTION'
Section: SCHEDULE RISKS.

Produce a markdown bulleted list of 6-10 specific schedule risks for
this project. Group under these ## sub-headings (omit empty groups):

## Design Risks
## Procurement Risks
## Construction Risks
## Interface & Energisation Risks
## External Risks

Each bullet format:
**<short risk title>** — one sentence describing the risk.
Optional second sentence describing schedule impact (days/weeks).

Do not include risks irrelevant to this scope. Do not propose
mitigations here — keep it to risk identification only.
SECTION;

        return [$system, self::userBlock($context)];
    }

    public function exclusions(BasisOfScheduleContext $context): array
    {
        $system = self::VOICE."\n\n".<<<'SECTION'
Section: EXCLUSIONS.

Produce a markdown bulleted list of 6-12 explicit exclusions from
this Basis of Schedule. Each bullet is one sentence.

Examples of legitimate exclusions to consider IF supported by the
context:
- Items free-issued by the client (BESS, transformer, etc.).
- Items inside vendor packages (HVAC, fire in vendor switchroom).
- Scope flags that are false.
- Standard EPC carve-outs: utility connection works beyond point
  of common coupling, force majeure events, owner-led acceptance
  delays, third-party utility approvals outside contractor control.

Be specific. If the project has BESS free-issued, say so explicitly.
If HVAC is in the vendor package, say so explicitly. Do not list
exclusions that contradict the scope context.
SECTION;

        return [$system, self::userBlock($context)];
    }

    private static function userBlock(BasisOfScheduleContext $context): string
    {
        return "Project facts (JSON):\n\n"
            .json_encode($context->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}