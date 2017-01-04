using System;
using System.Collections.Generic;
using System.Text;
using System.Collections.Specialized;

namespace RewriteCommentHtml
{
	public class XmlCommentHtmlRewriteWriter : System.Xml.XmlTextWriter
	{
		public XmlCommentHtmlRewriteWriter(System.IO.TextWriter w) : base(w) { }
		public XmlCommentHtmlRewriteWriter(string filename, Encoding encoding) : base(filename, encoding) { }
		public XmlCommentHtmlRewriteWriter(System.IO.Stream w, Encoding encoding) : base(w, encoding) { }

		string xpath = "";

		public override void WriteStartElement(string prefix, string localName, string ns)
		{
			xpath += "/" + localName;
			base.WriteStartElement(prefix, localName, ns);
		}

		public override void WriteEndElement()
		{
			xpath = xpath.Substring(0, xpath.LastIndexOf("/"));
			base.WriteEndElement();
		}

		public override void WriteCData(string text)
		{
			if (this.xpath.EndsWith("/comment/content"))
			{
				text = this.UpdateCommentHtml(text);
			}
			base.WriteCData(text);
		}

		public override void WriteFullEndElement()
		{
			xpath = xpath.Substring(0, xpath.LastIndexOf("/"));
			base.WriteFullEndElement();
		}

		public override void WriteValue(string value)
		{
			if (this.xpath.EndsWith("/comment/content"))
			{
				value = this.UpdateCommentHtml(value);
			}
			base.WriteValue(value);
		}

		private string UpdateCommentHtml(string text)
		{
			return System.Text.RegularExpressions.Regex.Replace(text, @"<\s*br\s*/?>", "", System.Text.RegularExpressions.RegexOptions.IgnoreCase);
		}

	}
}
