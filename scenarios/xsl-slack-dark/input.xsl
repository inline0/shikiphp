<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:output method="html" indent="yes"/>

  <!-- Render a list of books -->
  <xsl:template match="/catalog">
    <ul>
      <xsl:for-each select="book">
        <xsl:sort select="title"/>
        <li>
          <xsl:value-of select="title"/>
          <xsl:if test="@featured = 'true'">
            <strong> (featured)</strong>
          </xsl:if>
        </li>
      </xsl:for-each>
    </ul>
  </xsl:template>
</xsl:stylesheet>
