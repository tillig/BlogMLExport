<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:fn="http://www.w3.org/2005/xpath-functions" xmlns:xdt="http://www.w3.org/2005/xpath-datatypes">
	<xsl:output method="text"/>
	<xsl:template match="/">
$oldidtonewurlmap = array(<xsl:apply-templates select="blogmlidmap/map">
	<xsl:sort select="@old" data-type="number"/>
</xsl:apply-templates>
);
	</xsl:template>
	<xsl:template match="map">
	"<xsl:value-of select="@old" />" =&gt; "/archive/<xsl:value-of select="@year"/>/<xsl:value-of select="@month"/>/<xsl:value-of select="@day"/>/<xsl:value-of select="@new"/>.aspx"<xsl:if test="position() != last()">,</xsl:if></xsl:template>
</xsl:stylesheet>
