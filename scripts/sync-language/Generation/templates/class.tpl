<?xml version="1.0" encoding="utf-8"?>
<!-- $Revision$ -->
{BANNER}
<reference xml:id="class.{CLASS_ID}" role="{ROLE}" xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xi="http://www.w3.org/2001/XInclude">
 <title>{TITLE}</title>
 <titleabbrev>{CLASS_NAME}</titleabbrev>

 <partintro>

  <section xml:id="{CLASS_ID}.intro">
   &reftitle.intro;
   <simpara>
    {SUMMARY}
   </simpara>
   <simpara>
    This page is only a summary; the full documentation is in the PHP manual:
    <link xlink:href="{LINK}">{CLASS_NAME}</link>.
   </simpara>
  </section>

  <section xml:id="{CLASS_ID}.synopsis">
   {SYNOPSIS_TITLE}

{SYNOPSIS}

  </section>{PROPERTIES}

 </partintro>
{METHODS}
</reference>
