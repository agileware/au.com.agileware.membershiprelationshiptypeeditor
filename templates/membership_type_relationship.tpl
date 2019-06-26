{literal}
<script>
    var currentRelationshipTypes = [];
{/literal}

{foreach from=$selectedRelationshipTypes item=relationshipType}
    {literal}currentRelationshipTypes.push({/literal}'{$relationshipType}'{literal}){/literal}
{/foreach}

{literal}var formAction = {/literal}'{$formAction}'{literal};{/literal}

{literal}
</script>
{/literal}